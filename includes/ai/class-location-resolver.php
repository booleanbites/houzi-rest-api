<?php
/**
 * Resolves free-text location phrases (extracted by the AI) against the site's
 * location taxonomies. The model is never given the city list — it extracts a
 * canonicalized phrase; this class maps it to real term slugs.
 *
 * Resolution ladder (stop at first confident hit):
 *   1. normalize both sides
 *   2. exact match on name/slug
 *   3. alias table (abbreviations/nicknames — grown from logged misses)
 *   4. token-subset match ("New York" -> "New York City")
 *   5. fuzzy match (typos only)
 *
 * `search-properties` filters location/area/state by SLUG, so resolved values
 * are slugs.
 *
 * @package Houzi Mobile Api
 * @since Houzi 1.5.0
 * @author Adil Soomro
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Houzi_AI_Location_Resolver {

	const INDEX_TRANSIENT = 'houzi_ai_location_term_index';
	const ALIAS_OPTION    = 'houzi_ai_location_aliases';
	const MISS_OPTION     = 'houzi_ai_location_misses';
	const MISS_CAP        = 100;

	/** Ordered by specificity: a city hit beats a state hit for the same phrase. */
	private static $taxonomies = array( 'property_city', 'property_area', 'property_state', 'property_country' );

	/** Maps a matched taxonomy to the search-properties filter key. */
	public static $filter_key_for_taxonomy = array(
		'property_city'    => 'location',
		'property_area'    => 'area',
		'property_state'   => 'state',
		'property_country' => 'country',
	);

	/**
	 * Resolve a list of location phrases.
	 *
	 * @param array $phrases Free-text place names from the AI (already canonicalized).
	 * @return array ['resolved' => [['taxonomy','slug','name','phrase']],
	 *                'ambiguous' => [['phrase','candidates' => [...]]],
	 *                'unresolved' => [phrase, ...]]
	 */
	public static function resolve( $phrases ) {
		$result = array( 'resolved' => array(), 'ambiguous' => array(), 'unresolved' => array() );
		$index  = self::term_index();

		foreach ( (array) $phrases as $phrase ) {
			$phrase = sanitize_text_field( (string) $phrase );
			if ( '' === trim( $phrase ) ) {
				continue;
			}

			$matches = self::match_phrase( self::normalize( $phrase ), $index );

			if ( empty( $matches ) ) {
				self::log_miss( $phrase );
				$result['unresolved'][] = $phrase;
			} elseif ( 1 === count( $matches ) ) {
				$match             = $matches[0];
				$match['phrase']   = $phrase;
				$result['resolved'][] = $match;
			} else {
				$result['ambiguous'][] = array(
					'phrase'     => $phrase,
					'candidates' => array_slice( $matches, 0, 5 ),
				);
			}
		}

		return $result;
	}

	/**
	 * Run the ladder for one normalized phrase. Returns candidate list; a single
	 * entry means confident, several mean ambiguous.
	 */
	private static function match_phrase( $normalized, $index ) {
		// 2. Exact name/slug match, most specific taxonomy first.
		$exact = array();
		foreach ( self::$taxonomies as $taxonomy ) {
			foreach ( $index[ $taxonomy ] as $term ) {
				if ( $term['normalized'] === $normalized || $term['slug'] === $normalized ) {
					$exact[] = self::candidate( $taxonomy, $term );
				}
			}
			if ( ! empty( $exact ) ) {
				// A city exact-hit shouldn't be diluted by a state of the same name.
				return self::rank( $exact );
			}
		}

		// 3. Alias table.
		$aliases = self::aliases();
		if ( isset( $aliases[ $normalized ] ) ) {
			list( $taxonomy, $slug ) = array_pad( explode( ':', $aliases[ $normalized ], 2 ), 2, '' );
			if ( isset( $index[ $taxonomy ] ) ) {
				foreach ( $index[ $taxonomy ] as $term ) {
					if ( $term['slug'] === $slug ) {
						return array( self::candidate( $taxonomy, $term ) );
					}
				}
			}
		}

		// 4. Token subset: every query token appears in the term's tokens.
		$query_tokens = array_filter( explode( ' ', $normalized ) );
		$subset       = array();
		if ( ! empty( $query_tokens ) ) {
			foreach ( self::$taxonomies as $taxonomy ) {
				foreach ( $index[ $taxonomy ] as $term ) {
					$term_tokens = explode( ' ', $term['normalized'] );
					if ( 0 === count( array_diff( $query_tokens, $term_tokens ) ) ) {
						$subset[] = self::candidate( $taxonomy, $term );
					}
				}
			}
		}
		if ( ! empty( $subset ) ) {
			return self::rank( $subset );
		}

		// 5. Fuzzy — typos only: short distance on reasonably long phrases.
		if ( strlen( $normalized ) >= 5 ) {
			$fuzzy = array();
			foreach ( self::$taxonomies as $taxonomy ) {
				foreach ( $index[ $taxonomy ] as $term ) {
					$distance = levenshtein( $normalized, $term['normalized'] );
					if ( $distance > 0 && $distance <= 2 ) {
						$fuzzy[] = self::candidate( $taxonomy, $term, $distance );
					}
				}
			}
			if ( ! empty( $fuzzy ) ) {
				usort( $fuzzy, function ( $a, $b ) {
					return $a['distance'] - $b['distance'];
				} );
				// Only trust fuzzy when there is a clearly best hit.
				if ( 1 === count( $fuzzy ) || $fuzzy[0]['distance'] < $fuzzy[1]['distance'] ) {
					return array( $fuzzy[0] );
				}
				return $fuzzy;
			}
		}

		return array();
	}

	private static function candidate( $taxonomy, $term, $distance = 0 ) {
		return array(
			'taxonomy' => $taxonomy,
			'slug'     => $term['slug'],
			'name'     => $term['name'],
			'count'    => $term['count'],
			'distance' => $distance,
		);
	}

	/**
	 * Rank candidates; when one clearly dominates (only hit, or far more
	 * listings than the runner-up), collapse to a single confident match.
	 */
	private static function rank( $candidates ) {
		if ( count( $candidates ) <= 1 ) {
			return $candidates;
		}
		usort( $candidates, function ( $a, $b ) {
			return $b['count'] - $a['count'];
		} );
		// Dominance heuristic: 5x more listings than the runner-up -> auto-pick.
		if ( $candidates[0]['count'] >= 5 * max( 1, $candidates[1]['count'] ) ) {
			return array( $candidates[0] );
		}
		return $candidates;
	}

	/**
	 * Normalized term index for all location taxonomies, cached for an hour.
	 * Invalidated on term edits via houzi_ai_flush_location_index().
	 */
	public static function term_index() {
		$index = get_transient( self::INDEX_TRANSIENT );
		if ( is_array( $index ) ) {
			return $index;
		}

		$index = array();
		foreach ( self::$taxonomies as $taxonomy ) {
			$index[ $taxonomy ] = array();
			$terms              = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true ) );
			if ( is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$index[ $taxonomy ][] = array(
					'name'       => $term->name,
					'slug'       => $term->slug,
					'normalized' => self::normalize( $term->name ),
					'count'      => intval( $term->count ),
				);
			}
		}

		set_transient( self::INDEX_TRANSIENT, $index, HOUR_IN_SECONDS );
		return $index;
	}

	public static function flush_index() {
		delete_transient( self::INDEX_TRANSIENT );
	}

	public static function normalize( $text ) {
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
		$text = str_replace( array( '.', ',', '-', '_', "'" ), ' ', $text );
		$text = preg_replace( '/\s+/', ' ', $text );
		return trim( $text );
	}

	/**
	 * Alias map: normalized phrase => "taxonomy:slug".
	 * Curated in the admin AI tab; grown from the miss log.
	 */
	public static function aliases() {
		$aliases = get_option( self::ALIAS_OPTION );
		return is_array( $aliases ) ? $aliases : array();
	}

	/**
	 * Log an unresolved phrase so the admin can turn real misses into aliases.
	 */
	private static function log_miss( $phrase ) {
		$misses     = get_option( self::MISS_OPTION );
		$misses     = is_array( $misses ) ? $misses : array();
		$normalized = self::normalize( $phrase );

		if ( isset( $misses[ $normalized ] ) ) {
			$misses[ $normalized ]['count'] += 1;
			$misses[ $normalized ]['last']   = gmdate( 'Y-m-d' );
		} else {
			$misses[ $normalized ] = array( 'count' => 1, 'last' => gmdate( 'Y-m-d' ) );
		}

		// Cap: keep the most frequent misses.
		if ( count( $misses ) > self::MISS_CAP ) {
			uasort( $misses, function ( $a, $b ) {
				return $b['count'] - $a['count'];
			} );
			$misses = array_slice( $misses, 0, self::MISS_CAP, true );
		}

		update_option( self::MISS_OPTION, $misses, false );
	}
}
