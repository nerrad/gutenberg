<?php
/**
 * This file includes the GB_Scripts class which handles automatic i81n string attachment to registered js scripts.
 *
 * @package gutenberg
 * @since 2.6.x
 */

/**
 * This class handles queueing up only the translations for javascript files that have been enqueued for translation.
 *
 * @package gutenberg
 * @since   2.6.x
 */
class GB_Scripts {

	/**
	 * Will hold all registered i18n scripts.
	 *
	 * @var array
	 */
	private $registered_i18n = array();


	/**
	 * Used to hold queued translations for the chunks loading in a view.
	 *
	 * @var array
	 */
	private $queued_chunk_translations = array();


	/**
	 * Obtained from the generated json file from the all javascript using wp.i18n with a map of script handle names to
	 * translation strings.
	 *
	 * @var array
	 */
	private $i18n_map;
	
	
	/**
	 * GB_Scripts constructor.
	 *
	 * @param array() $i18n_map  An array of script handle names and the strings translated for those handles.  If not
	 *                            provided, the class will look for map in root of plugin with filename of
	 *                            'translation-map.json'.
	 */
	public function __construct( $i18n_map = array() ) {
		$this->set_chunk_map( $i18n_map );
		add_filter( 'print_scripts_array', array( $this, 'queue_i18n' ) );
	}
	
	
	/**
	 * Used to register a script that has i18n strings for its $handle
	 *
	 * @param string $handle The script handle reference.
	 * @param string $domain The i18n domain for the strings.
	 */
	public function register_script_i18n( $handle, $domain ) {
		$this->registered_i18n[ $handle ] = $domain;
	}


	/**
	 * Callback on print_scripts_array to listen for scripts enqueued and handle setting up the localized data.
	 *
	 * @param array $handles Array of registered script handles.
	 *
	 * @return array
	 */
	public function queue_i18n( $handles ) {
		if ( empty( $this->registered_i18n ) || empty( $this->i18n_map ) ) {
			return $handles;
		}
		foreach ( (array) $handles as $handle ) {
			$this->queue_i18n_translations_for_handle( $handle );
		}
		if ( $this->queued_chunk_translations ) {
			foreach ( $this->queued_chunk_translations as $handle => $translations_for_domain ) {
				$this->register_inline_script(
					$handle,
					$translations_for_domain['translations'],
					$translations_for_domain['domain']
				);
			}
		}
		return $handles;
	}


	/**
	 * Registers inline script with translations for given handle and domain.
	 *
	 * @param string $handle  Handle used to register javascript file containing translations.
	 * @param array  $translations  Array of string translations.
	 * @param string $domain    Domain for translations.  If left empty then strings are registered with the default
	 *                          domain for the javascript.
	 */
	private function register_inline_script( $handle, $translations, $domain = '' ) {
		$script = $domain ?
			'wp.i18n.setLocaleData( ' . json_encode( $translations ) . ', ' . $domain . ' );' :
			'wp.i18n.setLocaleData( ' . json_encode( $translations ) . ' );';
		wp_add_inline_script( $handle, $script, 'before' );
	}


	/**
	 * Queues up the translation strings for the given handle.
	 *
	 * @param string $handle  The script handle being queued up.
	 */
	private function queue_i18n_translations_for_handle( $handle ) {
		if ( isset( $this->registered_i18n[ $handle ] ) ) {
			$domain = $this->registered_i18n[ $handle ];
			$translations           = $this->get_jed_locale_data_for_domain_and_chunk( $handle, $domain );
			if ( count( $translations ) > 1 ) {
				$this->queued_chunk_translations[ $handle ] = array(
					'domain'       => $domain,
					'translations' => $translations
				);
			}
			unset( $this->registered_i18n[ $handle ] );
		}
	}


	/**
	 * Sets the internal i18n_map property.
	 *
	 * If $chunk_map is empty or not an array, will attempt to load a chunk map from a default named map.
	 *
	 * @param array $i18n_map  If provided, an array of translation strings indexed by script handle names they
	 *                         correspond to.
	 */
	private function set_chunk_map( $i18n_map ) {
		if ( empty( $i18n_map ) || ! is_array( $i18n_map ) ) {
			$i18n_map = json_decode(
				file_get_contents( gutenberg_dir_path() . 'translation-map.json' ),
				true
			);
		}
		$this->i18n_map = $i18n_map;
	}


	/**
	 * Get the jed locale data for a given $handle and domain
	 *
	 * @param string $handle The name for the script handle we want strings returned for.
	 * @param string $domain The i18n domain.
	 *
	 * @return array
	 */
	protected function get_jed_locale_data_for_domain_and_chunk( $handle, $domain ) {
		$translations = gutenberg_get_jed_locale_data( $domain );
		// get index for adding back after extracting strings for this $chunk.
		$index            = $translations[''];
		$translations     = $this->get_locale_data_matching_map(
			$this->get_original_strings_for_handle_from_map( $handle ),
			$translations
		);
		$translations[''] = $index;
		return $translations;
	}


	/**
	 * Get locale data for given strings from given translations
	 *
	 * @param array $string_set   This is the subset of strings (msgIds) we want to extract from the translations array.
	 * @param array $translations  Translation data to extra strings from.
	 *
	 * @return array
	 */
	protected function get_locale_data_matching_map( $string_set, $translations ) {
		if ( ! is_array( $string_set ) || ! is_array( $translations ) || empty( $string_set ) ) {
			return array();
		}
		// some strings with quotes in them will break on the array_flip, so making sure quotes in the string are
		// slashed also filter falsey values.
		$string_set = array_unique( array_filter( wp_slash( $string_set ) ) );
		return array_intersect_key( $translations, array_flip( $string_set ) );
	}


	/**
	 * Get original strings to translate for the given chunk from the map
	 *
	 * @param string $handle The script handle name to get strings from the map for.
	 *
	 * @return array
	 */
	protected function get_original_strings_for_handle_from_map( $handle ) {
		return isset( $this->i18n_map[ $handle ] ) ? $this->i18n_map[ $handle ] : array();
	}
}