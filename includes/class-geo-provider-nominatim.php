<?php
/**
 * Reverse Geolocation Provider.
 *
 * @package Simple_Location
 */

/**
 * Reverse Geolocation using Nominatim API.
 *
 * @since 1.0.0
 */
class Geo_Provider_Nominatim extends Geo_Provider {

	/**
	 * Constructor for the Abstract Class.
	 *
	 * The default version of this just sets the parameters.
	 *
	 * @param array $args {
	 *  Arguments.
	 *  @type string $api API Key.
	 *  @type float $latitude Latitude.
	 *  @type float $longitude Longitude.
	 *  @type float $altitude Altitude.
	 *  @type string $address Formatted Address String
	 *  @type int $reverse_zoom Reverse Zoom. Default 18.
	 *  @type string $user User name.
	 */
	public function __construct( $args = array() ) {
		$this->name = __( 'OpenStreetMap Nominatim', 'simple-location' );
		$this->slug = 'nominatim';
		parent::__construct( $args );
	}

	/**
	 * Returns elevation but there is no Nominatim Elevation API.
	 *
	 * @return float $elevation Elevation.
	 *
	 * @since 1.0.0
	 */
	public function elevation() {
		return 0;
	}

	/**
	 * Return an address.
	 *
	 * @return array $reverse microformats2 address elements in an array.
	 */
	public function reverse_lookup() {
		$args = array(
			'format'          => 'json',
			'extratags'       => '1',
			'addressdetails'  => '1',
			'lat'             => $this->latitude,
			'lon'             => $this->longitude,
			'zoom'            => $this->reverse_zoom,
			'accept-language' => get_bloginfo( 'language' ),
		);
		$url  = 'https://nominatim.openstreetmap.org/reverse';

		$json = $this->fetch_json( $url, $args );

		if ( is_wp_error( $json ) ) {
			return $json;
		}
		$address = $json['address'];
		return $this->address_to_mf( $address );
	}

	/**
	 * Convert address properties to mf2
	 *
	 * @param  array $address Raw JSON.
	 * @return array $reverse microformats2 address elements in an array.
	 */
	private function address_to_mf( $address ) {
		if ( 'us' === $address['country_code'] ) {
			$region = self::ifnot(
				$address,
				array(
					'state',
					'county',
				)
			);
		} else {
			$region = self::ifnot(
				$address,
				array(
					'county',
					'state',
				)
			);
		}
		$street  = ifset( $address['house_number'], '' ) . ' ';
		$street .= self::ifnot(
			$address,
			array(
				'road',
				'highway',
				'footway',
			)
		);
		$addr    = array(
			'name'             => self::ifnot(
				$address,
				array(
					'attraction',
					'building',
					'hotel',
					'address29',
					'address26',
				)
			),
			'street-address'   => $street,
			'extended-address' => self::ifnot(
				$address,
				array(
					'boro',
					'neighbourhood',
					'suburb',
				)
			),
			'locality'         => self::ifnot(
				$address,
				array(
					'hamlet',
					'village',
					'town',
					'city',
				)
			),
			'region'           => $region,
			'country-name'     => self::ifnot(
				$address,
				array(
					'country',
				)
			),
			'postal-code'      => self::ifnot(
				$address,
				array(
					'postcode',
				)
			),
			'country-code'     => strtoupper( $address['country_code'] ),

			'latitude'         => $this->latitude,
			'longitude'        => $this->longitude,
			'raw'              => $address,
		);
		if ( is_null( $addr['country-name'] ) ) {
			$file                 = trailingslashit( plugin_dir_path( __DIR__ ) ) . 'data/countries.json';
			$codes                = json_decode( file_get_contents( $file ), true );
			$addr['country-name'] = $codes[ $addr['country-code'] ];
		}
		$addr                 = array_filter( $addr );
		$addr['display-name'] = $this->display_name( $addr );
		$tz                   = $this->timezone();
		if ( $tz ) {
			$addr = array_merge( $addr, $tz );
		}
		return $addr;
	}


	/**
	 * Geocode address.
	 *
	 * @param  string $address String representation of location.
	 * @return array $reverse microformats2 address elements in an array.
	 */
	public function geocode( $address ) {
		$args = array(
			'q'               => $address,
			'format'          => 'jsonv2',
			'extratags'       => '1',
			'addressdetails'  => '1',
			'namedetails'     => '1',
			'accept-language' => get_bloginfo( 'language' ),
		);
		$url  = 'https://nominatim.openstreetmap.org/search';

		$json = $this->fetch_json( $url, $args );

		if ( is_wp_error( $json ) ) {
			return $json;
		}
		if ( wp_is_numeric_array( $json ) ) {
			$json = $json[0];
		}

		$address             = $json['address'];
		$return              = $this->address_to_mf( $address );
		$return['latitude']  = ifset( $json['lat'] );
		$return['longitude'] = ifset( $json['lon'] );
		if ( isset( $json['extratags'] ) ) {
			$return['url']   = ifset( $json['extratags']['website'] );
			$return['photo'] = ifset( $json['extratags']['image'] );
		}

		return array_filter( $return );
	}

}

register_sloc_provider( new Geo_Provider_Nominatim() );
