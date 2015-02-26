<?php

/*
 *
 * simply-rets-api-helper.php - Copyright (C) Reichert Brothers 2014
 * This file provides a class that has functions for retrieving and parsing
 * data from the remote retsd api.
 *
 *
*/

/* Code starts here */

class SimplyRetsApiHelper {



    public static function retrieveRetsListings( $params ) {
        $request_url      = SimplyRetsApiHelper::srRequestUrlBuilder( $params );
        $request_response = SimplyRetsApiHelper::srApiRequest( $request_url );
        $response_markup  = SimplyRetsApiHelper::srResidentialResultsGenerator( $request_response );

        return $response_markup;
    }


    public static function retrieveListingDetails( $listing_id ) {
        $request_url      = SimplyRetsApiHelper::srRequestUrlBuilder( $listing_id );
        $request_response = SimplyRetsApiHelper::srApiRequest( $request_url );
        $response_markup  = SimplyRetsApiHelper::srResidentialDetailsGenerator( $request_response );

        return $response_markup;
    }

    public static function retrieveWidgetListing( $listing_id ) {
        $request_url      = SimplyRetsApiHelper::srRequestUrlBuilder( $listing_id );
        $request_response = SimplyRetsApiHelper::srApiRequest( $request_url );
        $response_markup  = SimplyRetsApiHelper::srWidgetListingGenerator( $request_response );

        return $response_markup;
    }


    /*
     * This function build a URL from a set of parameters that we'll use to
     * requst our listings from the SimplyRETS API.
     *
     * @params is either an associative array in the form of [filter] => "val"
     * or it is a single listing id as a string, ie "123456".
     *
     * query variables for filtering will always come in as an array, so it
     * this is true, we can build a query off the standard /properties URL.
     *
     * If we do /not/ get an array, thenw we know we are requesting a single
     * listing, so we can just build the url with /properties/{ID}
     *
     * base url for local development: http://localhost:3001/properties
    */
    public static function srRequestUrlBuilder( $params ) {
        $authid   = get_option( 'sr_api_name' );
        $authkey  = get_option( 'sr_api_key' );
        $base_url = "http://{$authid}:{$authkey}@54.187.230.155/properties";

        if( is_array( $params ) ) {
            $filters_query = http_build_query( array_filter( $params ) );
            $request_url = "{$base_url}?{$filters_query}";
            return $request_url;

        } else {
            $request_url = $base_url . '/' . $params;
            return $request_url;

        }

    }


    /**
     * Make the request the SimplyRETS API. We try to use
     * cURL first, but if it's not enabled on the server, we
     * fall back to file_get_contents().
    */
    public static function srApiRequest( $url ) {
        $wp_version = get_bloginfo('version');
        $php_version = phpversion();

        $ua_string     = "SimplyRETSWP/1.2.0 Wordpress/{$wp_version} PHP/{$php_version}";
        $accept_header = "Accept: application/json; q=0.2, application/vnd.simplyrets-v0.1+json";

        if( is_callable( 'curl_init' ) ) {
            // init curl and set options
            $ch = curl_init();
            $curl_info = curl_version();
            $curl_version = $curl_info['version'];
            $headers[] = $accept_header;
            curl_setopt( $ch, CURLOPT_URL, $url );
            curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
            curl_setopt( $ch, CURLOPT_USERAGENT, $ua_string . " cURL/{$curl_version}" );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch, CURLOPT_HEADER, true );

            // make request to api
            $request = curl_exec( $ch );

            // get header size to parse out of response
            $header_size = curl_getinfo( $ch, CURLINFO_HEADER_SIZE );

            // separate header/body out of response
            $header      = substr( $request, 0, $header_size );
            $body        = substr( $request, $header_size );

            $pag_links = SimplyRetsApiHelper::srPaginationParser($header);

            // decode the reponse body
            $response_array = json_decode( $body );

            $srResponse = array();
            $srResponse['pagination'] = $pag_links;
            $srResponse['response'] = $response_array;;
            // close curl connection
            curl_close( $ch );
            return $srResponse;

        } else {
            $options = array(
                'http' => array(
                    'header' => $accept_header,
                    'user_agent' => $ua_string
                )
            );
            $context = stream_context_create( $options );
            $request = file_get_contents( $url, false, $context );
            $response_array = json_decode( $request );
            return $response_array;
        }

        if( $response_array === FALSE || empty($response_array) ) {
            $error =
                "Sorry, SimplyRETS could not complete this search." .
                "Please double check that your API credentials are valid " .
                "and that the search filters you used are correct. If this " .
                "is a new listing, you may also try back later.";
            $response_err = array(
                "error" => $error
            );
            return  $response_err;
        }

        return $response_array;
    }


    public static function srPaginationParser( $linkHeader ) {
        // get link val from header
        $pag_links = array();
        $name = 'Link';
        preg_match('/^Link: ([^\r\n]*)[\r\n]*$/m', $linkHeader, $matches);
        unset($matches[0]);
        foreach( $matches as $key => $val ) {
            $parts = explode( ",", $val );
            foreach( $parts as $key => $part ) {
                if( strpos( $part, 'rel="prev"' ) == true ) {
                    $part = trim( $part );
                    preg_match( '/^<(.*)>/', $part, $prevLink );
                    // $prevLink = $part;
                }
                if( strpos( $part, 'rel="next"' ) == true ) {
                    $part = trim( $part );
                    preg_match( '/^<(.*)>/', $part, $nextLink );
                }
            }
        }
        //  $nextLink = explode(",", $matches[1]);
        $prev_link = $prevLink[1];
        $next_link = $nextLink[1];
        $pag_links['prev'] = $prev_link;
        $pag_links['next'] = $next_link;
        return $pag_links;
    }


    public static function simplyRetsClientCss() {
        wp_register_style( 'simply-rets-client-css', plugins_url( 'assets/css/simply-rets-client.css', __FILE__ ) );
        wp_enqueue_style( 'simply-rets-client-css' );
    }

    public static function simplyRetsClientJs() {
        wp_register_script( 'simply-rets-client-js',
                            plugins_url( 'assets/js/simply-rets-client.js', __FILE__ ),
                            array('jquery')
        );
        wp_enqueue_script( 'simply-rets-client-js' );
        wp_register_script( 'simply-rets-galleria-js',
                            plugins_url( 'assets/galleria/galleria-1.4.2.min.js', __FILE__ ),
                            array('jquery')
        );
        wp_enqueue_script( 'simply-rets-galleria-js' );
    }


    /**
     * Experimental function not implemented yet. Should be
     * built out to show/hide fields based on whether or not
     * the specific listing has them.
     */
    public static function srDetailsTable($val, $name) {
        if( $val == "" ) {
            $val = "";
        } else {
            $val = <<<HTML
                <tr>
                  <td>$name</td>
                  <td>$val</td>
HTML;
        }
        return $val;
    }


    public static function srResidentialDetailsGenerator( $listing ) {
        $br = "<br>";
        $cont = "";
        $contact_page = get_option( 'sr_contact_page' );

        $listing = $listing['response'];
        /*
         * check for an error code in the array first, if it's
         * there, return it - no need to do anything else.
         * The error code comes from the UrlBuilder function.
        */
        if( $listing == NULL ) {
            $err = "SimplyRETS could not complete this search. Please check your " .
                "credentials and try again.";
            return $err;
        }
        if( array_key_exists( "error", $listing ) ) {
            $error = $listing->error;
            $cont .= "<hr><p>{$error}</p>";
            return $cont;
        }

        // stories
        $listing_stories = $listing->property->stories;
        $stories = SimplyRetsApiHelper::srDetailsTable($listing_stories, "Stories");
        // fireplaces
        $listing_fireplaces = $listing->property->fireplaces;
        $fireplaces = SimplyRetsApiHelper::srDetailsTable($listing_fireplaces, "Fireplaces");
        // Long
        $listing_longitude = $listing->geo->lng;
        $geo_longitude = SimplyRetsApiHelper::srDetailsTable($listing_longitude, "Longitude");
        // Long
        $listing_lat = $listing->geo->lat;
        $geo_latitude = SimplyRetsApiHelper::srDetailsTable($listing_lat, "Latitude");
        // County
        $listing_county = $listing->geo->county;
        $geo_county = SimplyRetsApiHelper::srDetailsTable($listing_county, "County");
        // mls area
        $listing_mlsarea = $listing->mls->area;
        $mls_area = SimplyRetsApiHelper::srDetailsTable($listing_mlsarea, "MLS Area");
        // tax data
        $listing_taxdata = $listing->tax->id;
        $tax_data = SimplyRetsApiHelper::srDetailsTable($listing_taxdata, "Tax Data");
        // school zone data
        $listing_schooldata = $listing->school->district;
        $school_data = SimplyRetsApiHelper::srDetailsTable($listing_schooldata, "School Data");
        // roof
        $listing_roof = $listing->property->roof;
        $roof = SimplyRetsApiHelper::srDetailsTable($listing_roof, "Roof");
        // subdivision
        $listing_subdivision = $listing->property->subdivision;
        $subdivision = SimplyRetsApiHelper::srDetailsTable($listing_subdivision, "Subdivision");



        // lot size
        $lotSize          = $listing->property->lotSize;
        if( $lotSize == 0 ) {
            $lot_sqft = 'n/a';
        } else {
            $lot_sqft    = number_format( $lotSize );
        }
        $area        = $listing->property->area; // might be empty
        if( $area == 0 ) {
            $area = 'n/a';
        } else {
            $area = number_format( $area );
        }


        // photos data (and set up slideshow markup)
        /**
         * We build the markup for our image gallery here. If classic is set explicity, we use
         * the classic gallery - otherwise we default to the fancy gallery
         */
        $photos = $listing->photos;
        if(empty($photos)) {
             $main_photo = plugins_url( 'assets/img/defprop.jpg', __FILE__ );
             $photo_gallery.= "  <img src='$main_photo'>";
        } else {
            $photo_gallery = '';
            if(get_option('sr_listing_gallery') == 'classic') {
                $main_photo = $photos[0];
                $photo_counter = 0;
                $more_photos = '<span id="sr-toggle-gallery">See more photos</span> |';
                $photo_gallery .= "<div class='sr-slider'><img class='sr-slider-img-act' src='$main_photo'>";
                foreach( $photos as $photo ) {
                    $photo_gallery.=
                        "<input class='sr-slider-input' type='radio' name='slide_switch' id='id$photo_counter' value='$photo' />";
                    $photo_gallery.= "<label for='id$photo_counter'>";
                    $photo_gallery.= "  <img src='$photo' width='100'>";
                    $photo_gallery.= "</label>";
                    $photo_counter++;
                }

            } else {
                $photo_gallery = '<div class="sr-gallery">';
                $more_photos = '';
                foreach( $photos as $photo ) {
                    $photo_gallery .= "<img src='$photo' data-title='$address'>";
                }
            }
            $photo_gallery .= "</div>";
        }

        // geographic data
        $geo_directions = $listing->geo->directions;
        if( $geo_directions == "" ) {
            $geo_directions = "";
        } else {
            $geo_directions = <<<HTML
              <thead>
                <tr>
                  <th colspan="2"><h5>Geographical Data</h5></th></tr></thead>
              <tbody>
                <tr>
                  <td>Direction</td>
                  <td>$geo_directions</td></tr>
HTML;
        }

        // list date and listing last modified
        if( get_option('sr_show_listingmeta') ) {
            $show_listing_meta = false;
        } else {
            $show_listing_meta = true;
        }

        $list_date_markup = '';
        if( $show_listing_meta == true ) {

            $list_date           = $listing->listDate;
            $list_date_formatted = date("M j, Y", strtotime($list_date));
            $date_formatted_markup = SimplyRetsApiHelper::srDetailsTable($list_date_formatted, "Listing Date");

            $listing_modified = $listing->modified; // TODO: format date
            $date_modified    = date("M j, Y", strtotime($listing_modified));
            $date_modified_markup = SimplyRetsApiHelper::srDetailsTable($date_modified, "Listing Last Modified");

            $list_date_markup .= $date_formatted_markup . $date_modified_markup;
            $listing_days_on_market = $listing->mls->daysOnMarket;
            $days_on_market = SimplyRetsApiHelper::srDetailsTable($listing_days_on_market, "Days on Market" );
        }

        // Amenities
        $bedrooms         = $listing->property->bedrooms;
        $bathsFull        = $listing->property->bathsFull;
        $interiorFeatures = $listing->property->interiorFeatures;
        $style            = $listing->property->style;
        $heating          = $listing->property->heating;
        $exteriorFeatures = $listing->property->exteriorFeatures;
        $yearBuilt        = $listing->property->yearBuilt;
        // listing meta information
        $disclaimer  = $listing->disclaimer;
        $listing_uid = $listing->mlsId;
        // street address info
        $postal_code   = $listing->address->postalCode;
        $country       = $listing->address->country;
        $address       = $listing->address->full;
        $city          = $listing->address->city;
        // Listing Data
        $listing_office   = $listing->office->name;
        $listing_price    = $listing->listPrice;
        $listing_USD      = '$' . number_format( $listing_price );


        if( get_option('sr_show_listing_remarks') ) {
            $show_remarks = false;
        } else {
            $show_remarks = true;
        }

        $remarks_markup = '';
        $remarks_table  = '';
        if( $show_remarks == true ) {
            $remarks = $listing->remarks;
            $remarks_markup = <<<HTML
            <div class="sr-remarks-details">
              <p>$remarks</p>
            </div>
HTML;
            $days_on_market = SimplyRetsApiHelper::srDetailsTable($remarks, "Remarks" );
        }

        // agent data
        $listing_agent_id    = $listing->agent->id;
        $listing_agent_name  = $listing->agent->firstName;
        $listing_agent_email = $listing->agent->contact->email;
        if( !$listing_agent_email == "" ) {
            $listing_agent_name = "<a href='mailto:$listing_agent_email'>$listing_agent_name</a>";
        }

        // mls information
        $mls_status     = $listing->mls->status;
        $galleria_theme = plugins_url('assets/galleria/themes/classic/galleria.classic.min.js', __FILE__);

        // listing markup
        $cont .= <<<HTML
          <div class="sr-details" style="text-align:left;">
            <p class="sr-details-links" style="clear:both;">
              $more_photos
              <span id="sr-listing-contact">
                <a href="$contact_page">Contact us about this listing</a>
              </span>
            </p>
            $photo_gallery
            <script>
              Galleria.loadTheme('$galleria_theme');
              Galleria.configure({
                  height: 475,
                  width:  "90%",
                  showinfo: false,
                  lightbox: true,
                  imageCrop: true,
                  imageMargin: 0,
                  fullscreenDoubleTap: true
              });
              Galleria.run('.sr-gallery');
            </script>
            <div class="sr-primary-details">
              <div class="sr-detail" id="sr-primary-details-beds">
                <h3>$bedrooms <small>Beds</small></h3>
              </div>
              <div class="sr-detail" id="sr-primary-details-baths">
                <h3>$bathsFull <small>Baths</small></h3>
              </div>
              <div class="sr-detail" id="sr-primary-details-size">
                <h3>$area <small>SqFt</small></h3>
              </div>
              <div class="sr-detail" id="sr-primary-details-status">
                <h3>$mls_status</h3>
              </div>
            </div>
            $remarks_markup
            <table style="width:100%;">
              <thead>
                <tr>
                  <th colspan="2"><h5>Listing Details</h5></th></tr></thead>
              <tbody>
                <tr>
                  <td>Price</td>
                  <td>$listing_USD</td></tr>
                <tr>
                  <td>Bedrooms</td>
                  <td>$bedrooms</td></tr>
                <tr>
                  <td>Full Bathrooms</td>
                  <td>$bathsFull</td></tr>
                <tr>
                  <td>Interior Features</td>
                  <td>$interiorFeatures</td></tr>
                <tr>
                  <td>Property Style</td>
                  <td>$style</td></tr>
                <tr>
                  <td>Heating</td>
                  <td>$heating</td></tr>
                $stories
                <tr>
                  <td>Exterior Features</td>
                  <td>$exteriorFeatures</td></tr>
                <tr>
                  <td>Year Built</td>
                  <td>$yearBuilt</td></tr>
                <tr>
                  <td>Lot Size</td>
                  <td>$lot_sqft SqFt</td></tr>
                $fireplaces
                $subdivision
                $roof
              </tbody>
                $geo_directions
                $geo_county
                $geo_latitude
                $geo_longitude
              </tbody>
              <thead>
                <tr>
                  <th colspan="2"><h5>Listing Meta Data</h5></th></tr></thead>
              <tbody>
                $list_date_markup
                $school_data
                <tr>
                  <td>Disclaimer</td>
                  <td>$disclaimer</td></tr>
                $tax_data
                <tr>
                  <td>Listing Id</td>
                  <td>$listing_uid</td></tr>
              </tbody>
              <thead>
                <tr>
                  <th colspan="2"><h5>Address Information</h5></th></tr></thead>
              <tbody>
                <tr>
                  <td>Postal Code</td>
                  <td>$postal_code</td></tr>
                <tr>
                  <td>Country Code</td>
                  <td>$country</td></tr>
                <tr>
                  <td>Address</td>
                  <td>$address</td></tr>
                <tr>
                  <td>City</td>
                  <td>$city</td></tr>
              </tbody>
              <thead>
                <tr>
                  <th colspan="2"><h5>Listing Information</h5></th></tr></thead>
              <tbody>
                <tr>
                  <td>Listing Office</td>
                  <td>$listing_office</td></tr>
                <tr>
                  <td>Listing Agent</td>
                  <td>$listing_agent_name</td></tr>
                $remarks_table
              </tbody>
              <thead>
                <tr>
                  <th colspan="2"><h5>Mls Information</h5></th></tr></thead>
              <tbody>
                $days_on_market
                <tr>
                  <td>Mls Status</td>
                  <td>$mls_status</td></tr>
                $mls_area
              </tbody>
            </table>
          </div>
HTML;

        return $cont;
    }


    public static function srResidentialResultsGenerator( $response ) {
        $br = "<br>";
        $cont = "";

        // echo '<pre><code>';
        // var_dump( $response );
        // echo '</pre></code>';

        $pagination = $response['pagination'];
        $response = $response['response'];

        if( $pagination['prev'] !== null && !empty($pagination['prev'] ) ) {
            $previous = $pagination['prev'];
            $siteUrl = get_home_url() . '/?sr-listings=sr-search&';
            $prev = str_replace( 'https://api.simplyrets.com/properties?', $siteUrl, $previous );
        }

        if( $pagination['next'] !== null && !empty($pagination['next'] ) ) {
            $nextLink = $pagination['next'];
            $siteUrl = get_home_url() . '/?sr-listings=sr-search&';
            $next = str_replace( 'https://api.simplyrets.com/properties?', $siteUrl, $nextLink );
        }

        /*
         * check for an error code in the array first, if it's
         * there, return it - no need to do anything else.
         * The error code comes from the UrlBuilder function.
        */
        if( $response == NULL ) {
            $err = "SimplyRETS could not complete this search. Please check your " .
                "credentials and try again.";
            return $err;
        }
        if( array_key_exists( "error", $response ) ) {
            $error = "SimplyRETS could not find any properties matching your criteria. Please try another search.";
            $response_markup = "<hr><p>{$error}</p><br>";
            return $response_markup;
        }

        $response_size = sizeof( $response );
        if( !array_key_exists( "0", $response ) ) {
            $response = array( $response );
        }

        if( get_option('sr_show_listingmeta') ) {
            $show_listing_meta = false;
        } else {
            $show_listing_meta = true;
        }

        foreach ( $response as $listing ) {
            // id
            $listing_uid      = $listing->mlsId;
            // Amenities
            $bedrooms    = $listing->property->bedrooms;
            $bathsFull   = $listing->property->bathsFull;
            $lotSize     = $listing->property->lotSize; // might be empty
            if( $lotSize == 0 ) {
                $lot_sqft = 'n/a';
            } else {
                $lot_sqft = number_format( $lotSize );
            }
            $area        = $listing->property->area; // might be empty
            if( $area == 0 ) {
                $area = 'n/a';
            } else {
                $area = number_format( $area );
            }

            $subdivision = $listing->property->subdivision;
            // year built
            $yearBuilt = $listing->property->yearBuilt;
            if( $yearBuilt == '' ) {
                $yearBuilt = 'n/a';
            }

            // listing data
            $listing_agent_id    = $listing->agent->id;
            $listing_agent_name  = $listing->agent->firstName;

            // show listing date if setting is on
            $list_date_markup = '';
            if( $show_listing_meta == true ) {
                $list_date        = $listing->listDate;
                $list_date_formatted = date("M j, Y", strtotime($list_date));
                $list_date_markup = <<<HTML
                    <li>
                      <span>Listed on $list_date_formatted</span>
                    </li>
HTML;
            }

            $listing_price    = $listing->listPrice;
            $listing_USD = '$' . number_format( $listing_price );
            // street address info
            $city    = $listing->address->city;
            $address = $listing->address->full;
            // listing photos
            $listingPhotos = $listing->photos;
            if( empty( $listingPhotos ) ) {
                $listingPhotos[0] = plugins_url( 'assets/img/defprop.jpg', __FILE__ );
            }
            $main_photo = trim($listingPhotos[0]);

            $listing_link = get_home_url() .
                "/?sr-listings=sr-single&listing_id=$listing_uid&listing_price=$listing_price&listing_title=$address";

            // append markup for this listing to the content
            $cont .= <<<HTML
              <hr>
              <div class="sr-listing">
                <a href="$listing_link">
                  <div class="sr-photo" style="background-image:url('$main_photo');">
                  </div>
                </a>
                <div class="sr-primary-data">
                  <a href="$listing_link">
                    <h4>$address
                    <span id="sr-price"><i>$listing_USD</i></span></h4>
                  </a>
                </div>
                <div class="sr-secondary-data">
                  <ul class="sr-data-column">
                    <li>
                      <span>$bedrooms Bedrooms</span>
                    </li>
                    <li>
                      <span>$bathsFull Full Baths</span>
                    </li>
                    <li>
                      <span>$area SqFt</span>
                    </li>
                    <li>
                      <span>Built in $yearBuilt</span>
                    </li>
                  </ul>
                  <ul class="sr-data-column">
                    <li>
                      <span>$subdivision</span>
                    </li>
                    <li>
                      <span>The City of $city</span>
                    </li>
                    <li>
                      <span>Listed by $listing_agent_name</span>
                    </li>
                    $list_date_markup
                  </ul>
                </div>
                <div style="clear:both;">
                  <a href="$listing_link">More details</a>
                </div>
              </div>
HTML;
        }

        $cont .= "<hr><p class='sr-pagination'><a href='{$prev}'>Prev</a> | <a href='{$next}'>Next</a></p>";
        $cont .= "<br><p><small><i>This information is believed to be accurate, but without any warranty.</i></small></p>";
        return $cont;
    }


    public static function srWidgetListingGenerator( $response ) {
        $br = "<br>";
        $cont = "";

        // echo '<pre><code>';
        // var_dump( $response );
        // echo '</pre></code>';

        /*
         * check for an error code in the array first, if it's
         * there, return it - no need to do anything else.
         * The error code comes from the UrlBuilder function.
        */
        $response = $response['response'];
        if( $response == NULL ) {
            $err = "SimplyRETS could not complete this search. Please check your " .
                "credentials and try again.";
            return $err;
        }
        if( array_key_exists( "error", $response ) ) {
            $error = $response['error'];
            $response_markup = "<hr><p>{$error}</p>";
            return $response_markup;
        }

        $response_size = sizeof( $response );
        if( $response_size <= 1 ) {
            $response = array( $response );
        }

        foreach ( $response as $listing ) {
            $listing_uid      = $listing->mlsId;
            // widget details
            $bedrooms    = $listing->property->bedrooms;
            $bathsFull   = $listing->property->bathsFull;
            $mls_status    = $listing->mls->status;
            $listing_remarks  = $listing->remarks;
            $listing_price = $listing->listPrice;
            $listing_USD   = '$' . number_format( $listing_price );

            // widget title
            $address = $listing->address->full;

            // widget photo
            $listingPhotos = $listing->photos;
            if( empty( $listingPhotos ) ) {
                $listingPhotos[0] = plugins_url( 'assets/img/defprop.jpg', __FILE__ );
            }
            $main_photo = $listingPhotos[0];

            // create link to listing
            $listing_link = get_home_url() . "/?sr-listings=sr-single&listing_id=$listing_uid&listing_price=$listing_price&listing_title=$address";

            // append markup for this listing to the content
            $cont .= <<<HTML
              <div class="sr-listing-wdgt">
                <a href="$listing_link">
                  <h5>$address
                    <small> - $listing_USD </small>
                  </h5>
                </a>
                <a href="$listing_link">
                  <img src="$main_photo" width="100%" alt="$address">
                </a>
                <div class="sr-listing-wdgt-primary">
                  <div id="sr-listing-wdgt-details">
                    <span>$bedrooms Bed | $bathsFull Bath | $mls_status </span>
                  </div>
                  <hr>
                  <div id="sr-listing-wdgt-remarks">
                    <p>$listing_remarks</p>
                  </div>
                </div>
                <div id="sr-listing-wdgt-btn">
                  <a href="$listing_link">
                    <button class="button btn">
                      More about this listing
                    </button>
                  </a>
                </div>
              </div>
HTML;

        }
        return $cont;
    }

}
