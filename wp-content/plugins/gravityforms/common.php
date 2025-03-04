<?php
if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class GFCommon {

	// deprecated; set to GFForms::$version in GFForms::init() for backwards compat
	public static $version = null;

	public static $tab_index = 1;
	public static $errors = array();
	public static $messages = array();

	public static function get_selection_fields( $form, $selected_field_id ) {

		$str = '';
		foreach ( $form['fields'] as $field ) {
			$input_type  = RGFormsModel::get_input_type( $field );
			$field_label = RGFormsModel::get_label( $field );
			if ( $input_type == 'checkbox' || $input_type == 'radio' || $input_type == 'select' ) {
				$selected = $field->id == $selected_field_id ? "selected='selected'" : '';
				$str .= "<option value='" . $field->id . "' " . $selected . '>' . $field_label . '</option>';
			}
		}

		return $str;
	}

	public static function is_numeric( $value, $number_format = '' ) {

		if ( $number_format == 'currency' ) {

			$number_format = self::is_currency_decimal_dot() ? 'decimal_dot' : 'decimal_comma';
			$value         = self::remove_currency_symbol( $value );
		}

		switch ( $number_format ) {
			case 'decimal_dot' :
				return preg_match( "/^(-?[0-9]{1,3}(?:,?[0-9]{3})*(?:\.[0-9]+)?)$/", $value );
				break;

			case 'decimal_comma' :
				return preg_match( "/^(-?[0-9]{1,3}(?:\.?[0-9]{3})*(?:,[0-9]+)?)$/", $value );
				break;

			default :
				return preg_match( "/^(-?[0-9]{1,3}(?:,?[0-9]{3})*(?:\.[0-9]{2})?)$/", $value ) || preg_match( "/^(-?[0-9]{1,3}(?:\.?[0-9]{3})*(?:,[0-9]{2})?)$/", $value );

		}
	}

	public static function remove_currency_symbol( $value, $currency = null ) {
		if ( $currency == null ) {
			$code = GFCommon::get_currency();
			if ( empty( $code ) ) {
				$code = 'USD';
			}

			$currency = RGCurrency::get_currency( $code );
		}

		$value = str_replace( $currency['symbol_left'], '', $value );
		$value = str_replace( $currency['symbol_right'], '', $value );

		//some symbols can't be easily matched up, so this will catch any of them
		$value = preg_replace( '/[^,.\d]/', '', $value );

		return $value;
	}

	public static function is_currency_decimal_dot( $currency = null ) {

		if ( $currency == null ) {
			$code = GFCommon::get_currency();
			if ( empty( $code ) ) {
				$code = 'USD';
			}

			$currency = RGCurrency::get_currency( $code );
		}

		return rgar( $currency, 'decimal_separator' ) == '.';
	}

	public static function trim_all( $text ) {
		$text = trim( $text );
		do {
			$prev_text = $text;
			$text      = str_replace( '  ', ' ', $text );
		} while ( $text != $prev_text );

		return $text;
	}

	public static function format_number( $number, $number_format, $currency = '', $include_thousands_sep = false ) {
		if ( ! is_numeric( $number ) ) {
			return $number;
		}

		//replacing commas with dots and dots with commas
		if ( $number_format == 'currency' ) {
			if ( empty( $currency ) ) {
				$currency = GFCommon::get_currency();
			}

			if ( false === class_exists( 'RGCurrency' ) ) {
				require_once( GFCommon::get_base_path() . '/currency.php' );
			}
			$currency = new RGCurrency( $currency );
			$number   = $currency->to_money( $number );
		} else {
			if ( $number_format == 'decimal_comma' ) {
				$dec_point     = ',';
				$thousands_sep = $include_thousands_sep ? '.' : '';
			} else {
				$dec_point     = '.';
				$thousands_sep = $include_thousands_sep ? ',' : '';
			}

			$is_negative = $number < 0;

			$number    = explode( '.', $number );
			$number[0] = number_format( absint( $number[0] ), 0, '', $thousands_sep );
			$number    = implode( $dec_point, $number );

			if ( $is_negative ) {
				$number = '-' . $number;
			}
		}

		return $number;
	}

	public static function recursive_add_index_file( $dir ) {
		if ( ! is_dir( $dir ) || is_link( $dir ) ) {
			return;
		}

		if ( ! ( $dp = opendir( $dir ) ) ) {
			return;
		}

		//ignores all errors
		set_error_handler( create_function( '', 'return 0;' ), E_ALL );

		//creates an empty index.html file
		if ( $f = fopen( $dir . '/index.html', 'w' ) ) {
			fclose( $f );
		}

		//restores error handler
		restore_error_handler();

		while ( ( false !== $file = readdir( $dp ) ) ) {
			if ( is_dir( "$dir/$file" ) && $file != '.' && $file != '..' ) {
				self::recursive_add_index_file( "$dir/$file" );
			}
		}

		closedir( $dp );
	}

    public static function add_htaccess_file(){

        $upload_root = GFFormsModel::get_upload_root();

        if ( ! is_dir( $upload_root ) ) {
            return;
        }
	    $htaccess_file = $upload_root . '/.htaccess_old';
	    if ( file_exists( $htaccess_file ) ) {
			unlink($htaccess_file);
	    }
	    $txt= '# Disable parsing of PHP for some server configurations. This file may be removed or modified on certain server configurations by using by the gform_upload_root_htaccess_rules filter. Please consult your system administrator before removing this file.
<Files *>
  SetHandler none
  SetHandler default-handler
  Options -ExecCGI
  RemoveHandler .cgi .php .php3 .php4 .php5 .phtml .pl .py .pyc .pyo
</Files>
<IfModule mod_php5.c>
  php_flag engine off
</IfModule>';
	    $rules = explode( "\n", $txt );

	    /**
	     * A filter to allow the modification/disabling of parsing certain PHP within Gravity Forms
	     *
	     * @param mixed $rules The Rules of what to parse or not to parse
	     */
	    $rules = apply_filters( 'gform_upload_root_htaccess_rules', $rules );
	    if ( ! empty( $rules ) ) {
		    if ( ! function_exists( 'insert_with_markers' ) ) {
			    require_once( ABSPATH . 'wp-admin/includes/misc.php' );
		    }
		    insert_with_markers( $htaccess_file, 'Gravity Forms', $rules );
	    }
    }

	public static function clean_number( $number, $number_format = '' ) {
		if ( rgblank( $number ) ) {
			return $number;
		}

		$decimal_char = '';
		if ( $number_format == 'decimal_dot' ) {
			$decimal_char = '.';
		} else if ( $number_format == 'decimal_comma' ) {
			$decimal_char = ',';
		}

		$float_number = '';
		$clean_number = '';
		$is_negative  = false;

		//Removing all non-numeric characters
		$array = str_split( $number );
		foreach ( $array as $char ) {
			if ( ( $char >= '0' && $char <= '9' ) || $char == ',' || $char == '.' ) {
				$clean_number .= $char;
			} else if ( $char == '-' ) {
				$is_negative = true;
			}
		}

		//Removing thousand separators but keeping decimal point
		$array = str_split( $clean_number );
		for ( $i = 0, $count = sizeof( $array ); $i < $count; $i ++ ) {
			$char = $array[ $i ];
			if ( $char >= '0' && $char <= '9' ) {
				$float_number .= $char;
			} else if ( empty( $decimal_char ) && ( $char == '.' || $char == ',' ) && strlen( $clean_number ) - $i <= 3 ) {
				$float_number .= '.';
			} else if ( $decimal_char == $char ) {
				$float_number .= '.';
			}
		}

		if ( $is_negative ) {
			$float_number = '-' . $float_number;
		}

		return $float_number;

	}

	public static function json_encode( $value ) {
		return json_encode( $value );
	}

	public static function json_decode( $str, $is_assoc = true ) {
		return json_decode( $str, $is_assoc );
	}

	//Returns the url of the plugin's root folder
	public static function get_base_url() {
		return plugins_url( '', __FILE__ );
	}

	//Returns the physical path of the plugin's root folder
	public static function get_base_path() {
		return dirname( __FILE__ );
	}

	public static function get_email_fields( $form ) {
		$fields = array();
		foreach ( $form['fields'] as $field ) {
			if ( $field->type == 'email' || $field->inputType == 'email' ) {
				$fields[] = $field;
			}
		}

		return $fields;
	}

	public static function truncate_middle( $text, $max_length ) {
		if ( strlen( $text ) <= $max_length ) {
			return $text;
		}

		$middle = intval( $max_length / 2 );

		return self::safe_substr( $text, 0, $middle ) . '...' . self::safe_substr( $text, strlen( $text ) - $middle, $middle );
	}

	public static function is_invalid_or_empty_email( $email ) {
		return empty( $email ) || ! self::is_valid_email( $email );
	}

	public static function is_valid_url( $url ) {
		$url = trim( $url );

		return ( ( strpos( $url, 'http://' ) === 0 || strpos( $url, 'https://' ) === 0 ) &&
			filter_var( $url, FILTER_VALIDATE_URL ) !== false );
	}

	public static function is_valid_email( $email ) {

		return filter_var( $email, FILTER_VALIDATE_EMAIL );
	}

	public static function is_valid_email_list( $email_list ) {
		$emails = explode( ',', $email_list );
		if ( ! is_array( $emails ) ){
			return false;
		}

		foreach( $emails as $email ){
			if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
				return false;
			}
		}

		return true;
	}

	public static function get_label( $field, $input_id = 0, $input_only = false, $allow_admin_label = true ) {
		return RGFormsModel::get_label( $field, $input_id, $input_only, $allow_admin_label );
	}

	public static function get_input( $field, $id ) {
		return RGFormsModel::get_input( $field, $id );
	}

	public static function insert_variables( $fields, $element_id, $hide_all_fields = false, $callback = '', $onchange = '', $max_label_size = 40, $exclude = null, $args = '', $class_name = '' ) {

		if ( $fields == null ) {
			$fields = array();
		}

		if ( $exclude == null ) {
			$exclude = array();
		}

		$exclude    = apply_filters( 'gform_merge_tag_list_exclude', $exclude, $element_id, $fields );
		$merge_tags = self::get_merge_tags( $fields, $element_id, $hide_all_fields, $exclude, $args );

		$onchange = empty( $onchange ) ? "InsertVariable('{$element_id}', '{$callback}');" : $onchange;
		$class    = trim( $class_name . ' gform_merge_tags' );

		?>

		<select id="<?php echo esc_attr( $element_id ); ?>_variable_select" onchange="<?php echo $onchange ?>" class="<?php echo esc_attr( $class ) ?>">
			<option value=''><?php esc_html_e( 'Insert Merge Tag' , 'gravityforms' ); ?></option>

			<?php foreach ( $merge_tags as $group => $group_tags ) {

				$group_label = rgar( $group_tags, 'label' );
				$tags        = rgar( $group_tags, 'tags' );

				if ( empty( $group_tags['tags'] ) ) {
					continue;
				}

				if ( $group_label ) {
					?>
					<optgroup label="<?php echo $group_label; ?>">
				<?php } ?>

				<?php foreach ( $tags as $tag ) { ?>
					<option value="<?php echo $tag['tag']; ?>"><?php echo $tag['label']; ?></option>
				<?php
				}
				if ( $group_label ) {
					?>
					</optgroup>
				<?php
				}
			} ?>

		</select>

	<?php
	}

	/**
	 * This function is used by the gfMergeTags JS object to get the localized label for non-field merge tags as well as
	 * for backwards compatibility with the gform_custom_merge_tags hook. Lastly, this plugin is used by the soon-to-be
	 * deprecated insert_variables() function as the new gfMergeTags object has not yet been applied to the Post Content
	 * Template setting.
	 *
	 * @param GF_Field[] $fields
	 * @param            $element_id
	 * @param bool       $hide_all_fields
	 * @param array      $exclude_field_types
	 * @param string     $option
	 *
	 * @return array
	 */
	public static function get_merge_tags( $fields, $element_id, $hide_all_fields = false, $exclude_field_types = array(), $option = '' ) {

		if ( $fields == null ) {
			$fields = array();
		}

		if ( $exclude_field_types == null ) {
			$exclude_field_types = array();
		}

		$required_fields = $optional_fields = $pricing_fields = array();
		$ungrouped       = $required_group = $optional_group = $pricing_group = $other_group = array();

		if ( ! $hide_all_fields ) {
			$ungrouped[] = array( 'tag' => '{all_fields}', 'label' => esc_html__( 'All Submitted Fields', 'gravityforms' ) );
		}

		// group fields by required, optional, and pricing
		foreach ( $fields as $field ) {

			if ( $field->displayOnly ) {
				continue;
			}

			$input_type = RGFormsModel::get_input_type( $field );

			// skip field types that should be excluded
			if ( is_array( $exclude_field_types ) && in_array( $input_type, $exclude_field_types ) ) {
				continue;
			}

			if ( $field->isRequired ) {

				switch ( $input_type ) {

					case 'name' :

						if ( $field->nameFormat == 'extended' ) {

							$prefix                   = GFCommon::get_input( $field, $field->id . '.2' );
							$suffix                   = GFCommon::get_input( $field, $field->id . '.8' );
							$optional_field           = $field;
							$optional_field['inputs'] = array( $prefix, $suffix );

							//Add optional name fields to the optional list
							$optional_fields[] = $optional_field;

							//Remove optional name field from required list
							unset( $field->inputs[0] );
							unset( $field->inputs[3] );

						}

						$required_fields[] = $field;

						break;

					default:
						$required_fields[] = $field;
				}
			} else {
				$optional_fields[] = $field;
			}

			if ( self::is_pricing_field( $field->type ) ) {
				$pricing_fields[] = $field;
			}
		}

		if ( ! empty( $required_fields ) ) {
			foreach ( $required_fields as $field ) {
				$required_group = array_merge( $required_group, self::get_field_merge_tags( $field, $option ) );
			}
		}

		if ( ! empty( $optional_fields ) ) {
			foreach ( $optional_fields as $field ) {
				$optional_group = array_merge( $optional_group, self::get_field_merge_tags( $field, $option ) );
			}
		}

		if ( ! empty( $pricing_fields ) ) {

			if ( ! $hide_all_fields ) {
				$pricing_group[] = array( 'tag' => '{pricing_fields}', 'label' => esc_html__( 'All Pricing Fields', 'gravityforms' ) );
			}

			foreach ( $pricing_fields as $field ) {
				$pricing_group = array_merge( $pricing_group, self::get_field_merge_tags( $field, $option ) );
			}
		}

		$other_group[] = array( 'tag' => '{ip}', 'label' => esc_html__( 'User IP Address', 'gravityforms' ) );
		$other_group[] = array( 'tag' => '{date_mdy}', 'label' => esc_html__( 'Date', 'gravityforms' ) . ' (mm/dd/yyyy)' );
		$other_group[] = array( 'tag' => '{date_dmy}', 'label' => esc_html__( 'Date', 'gravityforms' ) . ' (dd/mm/yyyy)' );
		$other_group[] = array( 'tag' => '{embed_post:ID}', 'label' => esc_html__( 'Embed Post/Page Id', 'gravityforms' ) );
		$other_group[] = array( 'tag' => '{embed_post:post_title}', 'label' => esc_html__( 'Embed Post/Page Title', 'gravityforms' ) );
		$other_group[] = array( 'tag' => '{embed_url}', 'label' => esc_html__( 'Embed URL', 'gravityforms' ) );
		$other_group[] = array( 'tag' => '{entry_id}', 'label' => esc_html__( 'Entry Id', 'gravityforms' ) );
		$other_group[] = array( 'tag' => '{entry_url}', 'label' => esc_html__( 'Entry URL', 'gravityforms' ) );
		$other_group[] = array( 'tag' => '{form_id}', 'label' => esc_html__( 'Form Id', 'gravityforms' ) );
		$other_group[] = array( 'tag' => '{form_title}', 'label' => esc_html__( 'Form Title', 'gravityforms' ) );
		$other_group[] = array( 'tag' => '{user_agent}', 'label' => esc_html__( 'HTTP User Agent', 'gravityforms' ) );
		$other_group[] = array( 'tag' => '{referer}', 'label' => esc_html__( 'HTTP Referer URL', 'gravityforms' ) );

		if ( self::has_post_field( $fields ) ) {
			$other_group[] = array( 'tag' => '{post_id}', 'label' => esc_html__( 'Post Id', 'gravityforms' ) );
			$other_group[] = array( 'tag' => '{post_edit_url}', 'label' => esc_html__( 'Post Edit URL', 'gravityforms' ) );
		}

		$other_group[] = array( 'tag' => '{user:display_name}', 'label' => esc_html__( 'User Display Name', 'gravityforms' ) );
		$other_group[] = array( 'tag' => '{user:user_email}', 'label' => esc_html__( 'User Email', 'gravityforms' ) );
		$other_group[] = array( 'tag' => '{user:user_login}', 'label' => esc_html__( 'User Login', 'gravityforms' ) );

		$form_id = isset($fields[0]) ? $fields[0]->formId : 0;

		$custom_group = apply_filters( 'gform_custom_merge_tags', array(), $form_id, $fields, $element_id );

		$merge_tags = array(
			'ungrouped' => array(
				'label' => false,
				'tags'  => $ungrouped,
			),
			'required'  => array(
				'label' => esc_html__( 'Required form fields', 'gravityforms' ),
				'tags'  => $required_group,
			),
			'optional'  => array(
				'label' => esc_html__( 'Optional form fields', 'gravityforms' ),
				'tags'  => $optional_group,
			),
			'pricing'   => array(
				'label' => esc_html__( 'Pricing form fields', 'gravityforms' ),
				'tags'  => $pricing_group,
			),
			'other'     => array(
				'label' => esc_html__( 'Other', 'gravityforms' ),
				'tags'  => $other_group,
			),
			'custom'    => array(
				'label' => esc_html__( 'Custom', 'gravityforms' ),
				'tags'  => $custom_group,
			)
		);

		return $merge_tags;
	}

	/**
	 * @param GF_Field $field
	 * @param string $option
	 * @return string
	 */
	public static function get_field_merge_tags( $field, $option = '' ) {

		$merge_tags = array();
		$tag_args   = RGFormsModel::get_input_type( $field ) == 'list' ? ":{$option}" : ''; //args currently only supported by list field

		$inputs = $field->get_entry_inputs();

		if ( is_array( $inputs ) ) {

			if ( RGFormsModel::get_input_type( $field ) == 'checkbox' ) {
				$value        = '{' . esc_html( GFCommon::get_label( $field, $field->id ) ) . ':' . $field->id . "{$tag_args}}";
				$merge_tags[] = array(
					'tag'   => $value,
					'label' => esc_html( GFCommon::get_label( $field, $field->id ) )
				);
			}

			foreach ( $field->inputs as $input ) {
				if ( RGFormsModel::get_input_type( $field ) == 'creditcard' ) {
					//only include the credit card type (field_id.4) and number (field_id.1)
					if ( $input['id'] == $field['id'] . '.1' || $input['id'] == $field['id'] . '.4' ) {
						$value        = '{' . esc_html( GFCommon::get_label( $field, $input['id'] ) ) . ':' . $input['id'] . "{$tag_args}}";
						$merge_tags[] = array(
							'tag'   => $value,
							'label' => esc_html( GFCommon::get_label( $field, $input['id'] ) )
						);
					}
				} else {
					$value        = '{' . esc_html( GFCommon::get_label( $field, $input['id'] ) ) . ':' . $input['id'] . "{$tag_args}}";
					$merge_tags[] = array(
						'tag'   => $value,
						'label' => esc_html( GFCommon::get_label( $field, $input['id'] ) )
					);
				}
			}
		} else {
			$value        = '{' . esc_html( GFCommon::get_label( $field ) ) . ':' . $field->id . "{$tag_args}}";
			$merge_tags[] = array(
				'tag'   => $value,
				'label' => esc_html( GFCommon::get_label( $field ) )
			);
		}

		return $merge_tags;
	}

	public static function insert_field_variable( $field, $max_label_size = 40, $args = '' ) {

		$tag_args = RGFormsModel::get_input_type( $field ) == 'list' ? ":{$args}" : ''; //args currently only supported by list field

		if ( is_array( $field->inputs ) ) {
			if ( RGFormsModel::get_input_type( $field ) == 'checkbox' ) {
				?>
				<option value='<?php echo '{' . esc_html( GFCommon::get_label( $field, $field->id ) ) . ':' . $field->id . "{$tag_args}}" ?>'><?php echo esc_html( GFCommon::get_label( $field, $field->id ) ) ?></option>
			<?php
			}

			foreach ( $field->inputs as $input ) {
				?>
				<option value='<?php echo '{' . esc_html( GFCommon::get_label( $field, $input['id'] ) ) . ':' . $input['id'] . "{$tag_args}}" ?>'><?php echo esc_html( GFCommon::get_label( $field, $input['id'] ) ) ?></option>
			<?php
			}
		} else {
			?>
			<option value='<?php echo '{' . esc_html( GFCommon::get_label( $field ) ) . ':' . $field->id . "{$tag_args}}" ?>'><?php echo esc_html( GFCommon::get_label( $field ) ) ?></option>
		<?php
		}
	}

	public static function insert_post_content_variables( $fields, $element_id, $callback, $max_label_size = 25 ) {
		// TODO: replace with class-powered merge tags
		$insert_variables_onchange = sprintf( "InsertPostContentVariable('%s', '%s');", esc_js( $element_id ), esc_js( $callback ) );
		self::insert_variables( $fields, $element_id, true, '', $insert_variables_onchange, $max_label_size, null, '', 'gform_content_template_merge_tags' );
		?>
		&nbsp;&nbsp;
		<select id="<?php echo $element_id ?>_image_size_select" onchange="InsertPostImageVariable('<?php echo esc_js( $element_id ); ?>', '<?php echo esc_js( $element_id ); ?>'); SetCustomFieldTemplate();" style="display:none;">
			<option value=""><?php esc_html_e( 'Select image size' , 'gravityforms' ) ?></option>
			<option value="thumbnail"><?php esc_html_e( 'Thumbnail' , 'gravityforms' ) ?></option>
			<option value="thumbnail:left"><?php esc_html_e( 'Thumbnail - Left Aligned' , 'gravityforms' ) ?></option>
			<option value="thumbnail:center"><?php esc_html_e( 'Thumbnail - Centered' , 'gravityforms' ) ?></option>
			<option value="thumbnail:right"><?php esc_html_e( 'Thumbnail - Right Aligned' , 'gravityforms' ) ?></option>

			<option value="medium"><?php esc_html_e( 'Medium' , 'gravityforms' ) ?></option>
			<option value="medium:left"><?php esc_html_e( 'Medium - Left Aligned' , 'gravityforms' ) ?></option>
			<option value="medium:center"><?php esc_html_e( 'Medium - Centered' , 'gravityforms' ) ?></option>
			<option value="medium:right"><?php esc_html_e( 'Medium - Right Aligned' , 'gravityforms' ) ?></option>

			<option value="large"><?php esc_html_e( 'Large' , 'gravityforms' ) ?></option>
			<option value="large:left"><?php esc_html_e( 'Large - Left Aligned' , 'gravityforms' ) ?></option>
			<option value="large:center"><?php esc_html_e( 'Large - Centered' , 'gravityforms' ) ?></option>
			<option value="large:right"><?php esc_html_e( 'Large - Right Aligned' , 'gravityforms' ) ?></option>

			<option value="full"><?php esc_html_e( 'Full Size' , 'gravityforms' ) ?></option>
			<option value="full:left"><?php esc_html_e( 'Full Size - Left Aligned' , 'gravityforms' ) ?></option>
			<option value="full:center"><?php esc_html_e( 'Full Size - Centered' , 'gravityforms' ) ?></option>
			<option value="full:right"><?php esc_html_e( 'Full Size - Right Aligned' , 'gravityforms' ) ?></option>
		</select>
	<?php
	}

	public static function insert_calculation_variables( $fields, $element_id, $onchange = '', $callback = '', $max_label_size = 40 ) {

		if ( $fields == null ) {
			$fields = array();
		}

		$onchange = empty( $onchange ) ? sprintf( "InsertVariable('%s', '%s');", esc_js( $element_id ), esc_js( $callback ) ): $onchange;
		$class    = 'gform_merge_tags';
		?>

		<select id="<?php echo esc_attr( $element_id ); ?>_variable_select" onchange="<?php echo $onchange ?>" class="<?php echo esc_attr( $class ) ?>">
			<option value=''><?php esc_html_e( 'Insert Merge Tag' , 'gravityforms' ); ?></option>
			<optgroup label="<?php esc_attr_e( 'Allowable form fields', 'gravityforms' ); ?>">

				<?php
				foreach ( $fields as $field ) {

					if ( ! self::is_valid_for_calcuation( $field ) ) {
						continue;
					}

					if ( RGFormsModel::get_input_type( $field ) == 'checkbox' ) {
						foreach ( $field->inputs as $input ) {
							?>
							<option value='<?php echo esc_attr( '{' . esc_html( GFCommon::get_label( $field, $input['id'] ) ) . ':' . $input['id'] . '}' ); ?>'><?php echo esc_html( GFCommon::get_label( $field, $input['id'] ) ) ?></option>
						<?php
						}
					} else {
						self::insert_field_variable( $field, $max_label_size );
					}
				}
				?>

			</optgroup>

			<?php
			$form_id = isset($forms[0]) ? $fields[0]->formId : 0;
			$custom_merge_tags = apply_filters( 'gform_custom_merge_tags', array(), $form_id, $fields, $element_id );

			if ( is_array( $custom_merge_tags ) && ! empty( $custom_merge_tags ) ) {
				?>

				<optgroup label="<?php esc_attr_e( 'Custom' , 'gravityforms' ); ?>">

					<?php foreach ( $custom_merge_tags as $custom_merge_tag ) { ?>

						<option value='<?php echo esc_attr( rgar( $custom_merge_tag, 'tag' ) ); ?>'><?php echo esc_html( rgar( $custom_merge_tag, 'label' ) ); ?></option>

					<?php } ?>

				</optgroup>

			<?php } ?>

		</select>

	<?php
	}

	private static function get_post_image_variable( $media_id, $arg1, $arg2, $is_url = false ) {

		if ( $is_url ) {
			$image = wp_get_attachment_image_src( $media_id, $arg1 );
			if ( $image ) {
				list( $src, $width, $height ) = $image;
			}

			return $src;
		}

		switch ( $arg1 ) {
			case 'title' :
				$media = get_post( $media_id );

				return $media->post_title;
			case 'caption' :
				$media = get_post( $media_id );

				return $media->post_excerpt;
			case 'description' :
				$media = get_post( $media_id );

				return $media->post_content;

			default :

				$img = wp_get_attachment_image( $media_id, $arg1, false, array( 'class' => "size-{$arg1} align{$arg2} wp-image-{$media_id}" ) );

				return $img;
		}
	}

	public static function replace_variables_post_image( $text, $post_images, $lead ) {

		preg_match_all( '/{[^{]*?:(\d+)(:([^:]*?))?(:([^:]*?))?(:url)?}/mi', $text, $matches, PREG_SET_ORDER );
		if ( is_array( $matches ) ) {
			foreach ( $matches as $match ) {
				$input_id = $match[1];

				//ignore fields that are not post images
				if ( ! isset( $post_images[ $input_id ] ) ) {
					continue;
				}

				//Reading alignment and 'url' parameters.
				//Format could be {image:5:medium:left:url} or {image:5:medium:url}
				$size_meta = empty( $match[3] ) ? 'full' : $match[3];
				$align     = empty( $match[5] ) ? 'none' : $match[5];
				if ( $align == 'url' ) {
					$align  = 'none';
					$is_url = true;
				} else {
					$is_url = rgar( $match, 6 ) == ':url';
				}

				$media_id = $post_images[ $input_id ];
				$value    = is_wp_error( $media_id ) ? '' : self::get_post_image_variable( $media_id, $size_meta, $align, $is_url );

				$text = str_replace( $match[0], $value, $text );
			}
		}

		return $text;
	}

	public static function implode_non_blank( $separator, $array ) {

		if ( ! is_array( $array ) ) {
			return '';
		}

		$ary = array();
		foreach ( $array as $item ) {
			if ( ! rgblank( $item ) ) {
				$ary[] = $item;
			}
		}

		return implode( $separator, $ary );
	}

	public static function format_variable_value( $value, $url_encode, $esc_html, $format, $nl2br = true ) {
		if ( $esc_html ) {
			$value = esc_html( $value );
		}

		if ( $format == 'html' && $nl2br ) {
			$value = nl2br( $value );
		}

		if ( $url_encode ) {
			$value = urlencode( $value );
		}

		return $value;
	}

	public static function replace_variables( $text, $form, $lead, $url_encode = false, $esc_html = true, $nl2br = true, $format = 'html' ) {

		$text = $nl2br ? nl2br( $text ) : $text;

		$text = apply_filters( 'gform_pre_replace_merge_tags', $text, $form, $lead, $url_encode, $esc_html, $nl2br, $format );

		//Replacing field variables: {FIELD_LABEL:FIELD_ID} {My Field:2}
		preg_match_all( '/{[^{]*?:(\d+(\.\d+)?)(:(.*?))?}/mi', $text, $matches, PREG_SET_ORDER );
		if ( is_array( $matches ) ) {
			foreach ( $matches as $match ) {
				$input_id = $match[1];

				$field = RGFormsModel::get_field( $form, $input_id );

				if ( ! $field instanceof GF_Field ) {
					$field = GF_Fields::create( $field );
				}

				$value     = RGFormsModel::get_lead_field_value( $lead, $field );
				$raw_value = $value;

				if ( is_array( $value ) ) {
					$value = rgar( $value, $input_id );
				}

				$value = self::format_variable_value( $value, $url_encode, $esc_html, $format, $nl2br );

				$modifier = strtolower( rgar( $match, 4 ) );

				$value = $field->get_value_merge_tag( $value, $input_id, $lead, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br );

				if ( $modifier == 'label' ) {
					$value = empty( $value ) ? '' : $field->label;
				} else if ( $modifier == 'qty' && $field->type == 'product' ) {
					//getting quantity associated with product field
					$products = self::get_product_fields( $form, $lead, false, false );
					$value    = 0;
					foreach ( $products['products'] as $product_id => $product ) {
						if ( $product_id == $field->id ) {
							$value = $product['quantity'];
						}
					}
				}

				//Encoding left curly bracket so that merge tags entered in the front end are displayed as is and not 'executed'
				$value = self::encode_merge_tag( $value );

				//filter can change merge tab variable
				$value = apply_filters( 'gform_merge_tag_filter', $value, $input_id, $modifier, $field, $raw_value );
				if ( $value === false ) {
					$value = '';
				}

				$text = str_replace( $match[0], $value, $text );
			}
		}

		//replacing global variables
		//form title
		$text = str_replace( '{form_title}', $url_encode ? urlencode( $form['title'] ) : $form['title'], $text );

		$matches = array();
		preg_match_all( "/{all_fields(:(.*?))?}/", $text, $matches, PREG_SET_ORDER );
		foreach ( $matches as $match ) {
			$options         = explode( ',', rgar( $match, 2 ) );
			$use_value       = in_array( 'value', $options );
			$display_empty   = in_array( 'empty', $options );
			$use_admin_label = in_array( 'admin', $options );

			//all submitted fields using text
			if ( strpos( $text, $match[0] ) !== false ) {
				$text = str_replace( $match[0], self::get_submitted_fields( $form, $lead, $display_empty, ! $use_value, $format, $use_admin_label, 'all_fields', rgar( $match, 2 ) ), $text );
			}
		}

		//all submitted fields including empty fields
		if ( strpos( $text, '{all_fields_display_empty}' ) !== false ) {
			$text = str_replace( '{all_fields_display_empty}', self::get_submitted_fields( $form, $lead, true, true, $format, false, 'all_fields_display_empty' ), $text );
		}

		//pricing fields
		$pricing_matches = array();
		preg_match_all( "/{pricing_fields(:(.*?))?}/", $text, $pricing_matches, PREG_SET_ORDER );
		foreach ( $pricing_matches as $match ) {
			$options 		 = explode( ',', rgar( $match, 2 ) );
			$use_value       = in_array( 'value', $options );
			$use_admin_label = in_array( 'admin', $options );

			//all submitted pricing fields using text
			if ( strpos( $text, $match[0] ) !== false ) {
				$pricing_fields = self::get_submitted_pricing_fields( $form, $lead, $format, ! $use_value, $use_admin_label );

				if ( $format == 'html' ) {
					$text = str_replace(
						$match[0], '<table width="99%" border="0" cellpadding="1" cellspacing="0" bgcolor="#EAEAEA">
															   <tr><td>
																	<table width="100%" border="0" cellpadding="5" cellspacing="0" bgcolor="#FFFFFF">' .
											$pricing_fields .
											'</table>
															   </tr></td>
														 </table>',
						$text
					);
				}
				else {
					$text = str_replace( $match[0], $pricing_fields, $text );
				}
			}
		}

		//form id
		$text = str_replace( '{form_id}', $url_encode ? urlencode( $form['id'] ) : $form['id'], $text );

		//entry id
		$text = str_replace( '{entry_id}', $url_encode ? urlencode( rgar( $lead, 'id' ) ) : rgar( $lead, 'id' ), $text );

		//entry url
		$entry_url = get_bloginfo( 'wpurl' ) . '/wp-admin/admin.php?page=gf_entries&view=entry&id=' . $form['id'] . '&lid=' . rgar( $lead, 'id' );
		$text      = str_replace( '{entry_url}', $url_encode ? urlencode( $entry_url ) : $entry_url, $text );

		//post id
		$text = str_replace( '{post_id}', $url_encode ? urlencode( rgar( $lead, 'post_id' ) ) : rgar( $lead, 'post_id' ), $text );

		//admin email
		$wp_email = get_bloginfo( 'admin_email' );
		$text     = str_replace( '{admin_email}', $url_encode ? urlencode( $wp_email ) : $wp_email, $text );

		//post edit url
		$post_url = get_bloginfo( 'wpurl' ) . '/wp-admin/post.php?action=edit&post=' . rgar( $lead, 'post_id' );
		$text     = str_replace( '{post_edit_url}', $url_encode ? urlencode( $post_url ) : $post_url, $text );

		$text = self::replace_variables_prepopulate( $text, $url_encode, $lead, $esc_html );

		// hook allows for custom merge tags
		$text = apply_filters( 'gform_replace_merge_tags', $text, $form, $lead, $url_encode, $esc_html, $nl2br, $format );

		// TODO: Deprecate the 'gform_replace_merge_tags' and replace it with a call to the 'gform_merge_tag_filter'
		//$text = apply_filters('gform_merge_tag_filter', $text, false, false, false );

		$text = self::decode_merge_tag( $text );

		return $text;
	}

	public static function encode_merge_tag( $text ) {
		return str_replace( '{', '&#x7b;', $text );
	}

	public static function decode_merge_tag( $text ) {
		return str_replace( '&#x7b;', '{', $text );
	}

	public static function format_post_category( $value, $use_id ) {

		list( $item_value, $item_id ) = rgexplode( ':', $value, 2 );

		if ( $use_id && ! empty( $item_id ) ) {
			$item_value = $item_id;
		}

		return $item_value;
	}

	public static function get_embed_post() {
		global $embed_post, $post, $wp_query;

		if ( $embed_post ) {
			return $embed_post;
		}

		if ( ! rgempty( 'gform_embed_post' ) ) {
			$post_id    = absint( rgpost( 'gform_embed_post' ) );
			$embed_post = get_post( $post_id );
		} else if ( $wp_query->is_in_loop ) {
			$embed_post = $post;
		} else {
			$embed_post = array();
		}
	}

	public static function get_ul_classes( $form ){

		$description_class = rgar( $form, 'descriptionPlacement' ) == 'above' ? 'description_above' : 'description_below';
		$sublabel_class    = rgar( $form, 'subLabelPlacement' ) == 'above' ? 'form_sublabel_above' : 'form_sublabel_below';
		$label_class       = rgempty( 'labelPlacement', $form ) ? 'top_label' : rgar( $form, 'labelPlacement' );

		$css_class = preg_replace( '/\s+/', ' ', "gform_fields {$label_class} {$sublabel_class} {$description_class}" ); //removing extra spaces

		return $css_class;
	}


	public static function replace_variables_prepopulate( $text, $url_encode = false, $entry = false, $esc_html = false ) {

		//embed url
		$current_page_url = RGFormsModel::get_current_page_url();
		if ( $esc_html ) {
			$current_page_url = esc_html( $current_page_url );
		}
		if ( $url_encode ) {
			$current_page_url = urlencode( $current_page_url );
		}
		$text = str_replace( '{embed_url}', $current_page_url, $text );

		$local_timestamp = self::get_local_timestamp( time() );

		//date (mm/dd/yyyy)
		$local_date_mdy = date_i18n( 'm/d/Y', $local_timestamp, true );
		$text           = str_replace( '{date_mdy}', $url_encode ? urlencode( $local_date_mdy ) : $local_date_mdy, $text );

		//date (dd/mm/yyyy)
		$local_date_dmy = date_i18n( 'd/m/Y', $local_timestamp, true );
		$text           = str_replace( '{date_dmy}', $url_encode ? urlencode( $local_date_dmy ) : $local_date_dmy, $text );

		// ip
		$ip = isset( $entry['ip'] ) ? $entry['ip'] : GFFormsModel::get_ip();
		$text = str_replace( '{ip}', $url_encode ? urlencode( $ip ) : $ip, $text );

		global $post;
		$post_array = self::object_to_array( $post );
		preg_match_all( "/\{embed_post:(.*?)\}/", $text, $matches, PREG_SET_ORDER );
		foreach ( $matches as $match ) {
			$full_tag = $match[0];
			$property = $match[1];
			$text     = str_replace( $full_tag, $url_encode ? urlencode( $post_array[ $property ] ) : $post_array[ $property ], $text );
		}

		//embed post custom fields
		preg_match_all( "/\{custom_field:(.*?)\}/", $text, $matches, PREG_SET_ORDER );
		foreach ( $matches as $match ) {

			$full_tag           = $match[0];
			$custom_field_name  = $match[1];
			$custom_field_value = ! empty( $post_array['ID'] ) ? get_post_meta( $post_array['ID'], $custom_field_name, true ) : '';
			$text               = str_replace( $full_tag, $url_encode ? urlencode( $custom_field_value ) : $custom_field_value, $text );
		}

		//user agent
		$user_agent = RGForms::get( 'HTTP_USER_AGENT', $_SERVER );
		if ( $esc_html ) {
			$user_agent = esc_html( $user_agent );
		}
		if ( $url_encode ) {
			$user_agent = urlencode( $user_agent );
		}
		$text = str_replace( '{user_agent}', $user_agent, $text );

		//referrer
		$referer = RGForms::get( 'HTTP_REFERER', $_SERVER );
		if ( $esc_html ) {
			$referer = esc_html( $referer );
		}
		if ( $url_encode ) {
			$referer = urlencode( $referer );
		}
		$text = str_replace( '{referer}',  $referer, $text );

		//logged in user info
		global $userdata, $wp_version, $current_user;
		$user_array = self::object_to_array( $userdata );

		preg_match_all( "/\{user:(.*?)\}/", $text, $matches, PREG_SET_ORDER );
		foreach ( $matches as $match ) {
			$full_tag = $match[0];
			$property = $match[1];

			$value = version_compare( $wp_version, '3.3', '>=' ) ? $current_user->get( $property ) : $user_array[ $property ];
			$value = $url_encode ? urlencode( $value ) : $value;

			$text = str_replace( $full_tag, $value, $text );
		}

		$text = apply_filters( 'gform_replace_merge_tags', $text, false, $entry, $url_encode, $esc_html, false, false );

		return $text;
	}

	public static function object_to_array( $object ) {
		$array = array();
		if ( ! empty( $object ) ) {
			foreach ( $object as $member => $data ) {
				$array[ $member ] = $data;
			}
		}

		return $array;
	}

	public static function is_empty_array( $val ) {
		if ( ! is_array( $val ) ) {
			$val = array( $val );
		}

		$ary = array_values( $val );
		foreach ( $ary as $item ) {
			if ( ! rgblank( $item ) ) {
				return false;
			}
		}

		return true;
	}

	public static function get_submitted_fields( $form, $lead, $display_empty = false, $use_text = false, $format = 'html', $use_admin_label = false, $merge_tag = '', $options = '' ) {

		$field_data = '';
		if ( $format == 'html' ) {
			$field_data = '<table width="99%" border="0" cellpadding="1" cellspacing="0" bgcolor="#EAEAEA"><tr><td>
                            <table width="100%" border="0" cellpadding="5" cellspacing="0" bgcolor="#FFFFFF">
                            ';
		}

		$options_array           = explode( ',', $options );
		$no_admin                = in_array( 'noadmin', $options_array );
		$no_hidden               = in_array( 'nohidden', $options_array );
		$display_product_summary = false;

		foreach ( $form['fields'] as $field ) {
			$field_value = '';

			$field_label = $use_admin_label && ! empty( $field->adminLabel ) ? $field->adminLabel : esc_html( GFCommon::get_label( $field, 0, false, $use_admin_label ) );

			switch ( $field->type ) {
				case 'captcha' :
					break;

				case 'section' :

					if ( GFFormsModel::is_field_hidden( $form, $field, array(), $lead ) ){
						continue;
					}

					if ( ( ! GFCommon::is_section_empty( $field, $form, $lead ) || $display_empty ) && ! $field->adminOnly ) {

						switch ( $format ) {
							case 'text' :
								$field_value = "--------------------------------\n{$field_label}\n\n";
								break;

							default:
								$field_value = sprintf(
									'<tr>
                                        	<td colspan="2" style="font-size:14px; font-weight:bold; background-color:#EEE; border-bottom:1px solid #DFDFDF; padding:7px 7px">%s</td>
	                                   </tr>
	                                   ', $field_label
								);
								break;
						}
					}

					$field_value = apply_filters( 'gform_merge_tag_filter', $field_value, $merge_tag, $options, $field, $field_label );

					$field_data .= $field_value;

					break;
				case 'password' :
					//ignore password fields
					break;

				default :

					if ( self::is_product_field( $field->type ) ) {

						// ignore product fields as they will be grouped together at the end of the grid
						$display_product_summary = apply_filters( 'gform_display_product_summary', true, $field, $form, $lead );
						if ( $display_product_summary ) {
							continue;
						}
					} else if ( GFFormsModel::is_field_hidden( $form, $field, array(), $lead ) ) {
						// ignore fields hidden by conditional logic
						continue;
					}

					$raw_field_value = RGFormsModel::get_lead_field_value( $lead, $field );
					$field_value     = GFCommon::get_lead_field_display( $field, $raw_field_value, rgar( $lead, 'currency' ), $use_text, $format, 'email' );

					$display_field = true;
					//depending on parameters, don't display adminOnly or hidden fields
					if ( $no_admin && $field->adminOnly ) {
						$display_field = false;
					} else if ( $no_hidden && RGFormsModel::get_input_type( $field ) == 'hidden' ) {
						$display_field = false;
					}

					//if field is not supposed to be displayed, pass false to filter. otherwise, pass field's value
					if ( ! $display_field ) {
						$field_value = false;
					}

					$field_value = apply_filters( 'gform_merge_tag_filter', $field_value, $merge_tag, $options, $field, $raw_field_value );

					if ( $field_value === false ) {
						continue;
					}

					if ( ! empty( $field_value ) || strlen( $field_value ) > 0 || $display_empty ) {
						switch ( $format ) {
							case 'text' :
								$field_data .= "{$field_label}: {$field_value}\n\n";
								break;

							default:

								$field_data .= sprintf(
										'<tr bgcolor="%3$s">
		                                    <td colspan="2">
		                                        <font style="font-family: sans-serif; font-size:12px;"><strong>%1$s</strong></font>
		                                    </td>
		                               </tr>
		                               <tr bgcolor="%4$s">
		                                    <td width="20">&nbsp;</td>
		                                    <td>
		                                        <font style="font-family: sans-serif; font-size:12px;">%2$s</font>
		                                    </td>
		                               </tr>
		                               ', $field_label, empty( $field_value ) && strlen( $field_value ) == 0 ? '&nbsp;' : $field_value, esc_attr( apply_filters( 'gform_email_background_color_label', '#EAF2FA', $field, $lead ) ), esc_attr( apply_filters( 'gform_email_background_color_data', '#FFFFFF', $field, $lead ) )
								);
								break;
						}
					}
			}
		}

		if ( $display_product_summary ) {
			$field_data .= self::get_submitted_pricing_fields( $form, $lead, $format, $use_text, $use_admin_label );
		}

		if ( $format == 'html' ) {
			$field_data .= '</table>
                        </td>
                   </tr>
               </table>';
		}

		return $field_data;
	}

	public static function get_submitted_pricing_fields( $form, $lead, $format, $use_text = true, $use_admin_label = false ) {
		$form_id     = $form['id'];
		$order_label = gf_apply_filters( 'gform_order_label', $form_id, esc_html__( 'Order' , 'gravityforms' ), $form['id'] );
		$products    = GFCommon::get_product_fields( $form, $lead, $use_text, $use_admin_label );
		$total       = 0;
		$field_data  = '';

		switch ( $format ) {
			case 'text' :
				if ( ! empty( $products['products'] ) ) {
					$field_data = "--------------------------------\n" . $order_label . "\n\n";
					foreach ( $products['products'] as $product ) {
						$product_name = $product['quantity'] . ' ' . $product['name'];
						$price        = self::to_number( $product['price'] );
						if ( ! empty( $product['options'] ) ) {
							$product_name .= ' (';
							$options = array();
							foreach ( $product['options'] as $option ) {
								$price += self::to_number( $option['price'] );
								$options[] = $option['option_name'];
							}
							$product_name .= implode( ', ', $options ) . ')';
						}
						$subtotal = floatval( $product['quantity'] ) * $price;
						$total += $subtotal;

						$field_data .= "{$product_name}: " . self::to_money( $subtotal, $lead['currency'] ) . "\n\n";
					}
					$total += floatval( $products['shipping']['price'] );

					if ( ! empty( $products['shipping']['name'] ) ) {
						$field_data .= $products['shipping']['name'] . ': ' . self::to_money( $products['shipping']['price'], $lead['currency'] ) . "\n\n";
					}

					$field_data .= esc_html__( 'Total' , 'gravityforms' ) . ': ' . self::to_money( $total, $lead['currency'] ) . "\n\n";
				}
				break;


			default :
				if ( ! empty( $products['products'] ) ) {
					$field_data = '<tr bgcolor="#EAF2FA">
                            <td colspan="2">
                                <font style="font-family: sans-serif; font-size:12px;"><strong>' . $order_label . '</strong></font>
                            </td>
                       </tr>
                       <tr bgcolor="#FFFFFF">
                            <td width="20">&nbsp;</td>
                            <td>
                                <table cellspacing="0" width="97%" style="border-left:1px solid #DFDFDF; border-top:1px solid #DFDFDF">
                                <thead>
                                    <th style="background-color:#F4F4F4; border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; font-family: sans-serif; font-size:12px; text-align:left">' . gf_apply_filters( 'gform_product', $form_id, esc_html__( 'Product' , 'gravityforms' ), $form_id ) . '</th>
                                    <th style="background-color:#F4F4F4; border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; width:50px; font-family: sans-serif; font-size:12px; text-align:center">' . gf_apply_filters( 'gform_product_qty', $form_id, esc_html__( 'Qty' , 'gravityforms' ), $form_id ) . '</th>
                                    <th style="background-color:#F4F4F4; border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; width:155px; font-family: sans-serif; font-size:12px; text-align:left">' . gf_apply_filters( 'gform_product_unitprice', $form_id, esc_html__( 'Unit Price' , 'gravityforms' ), $form_id ) . '</th>
                                    <th style="background-color:#F4F4F4; border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; width:155px; font-family: sans-serif; font-size:12px; text-align:left">' . gf_apply_filters( 'gform_product_price', $form_id, esc_html__( 'Price' , 'gravityforms' ), $form_id ) . '</th>
                                </thead>
                                <tbody>';


					foreach ( $products['products'] as $product ) {

						$field_data .= '<tr>
                                                        <td style="border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; font-family: sans-serif; font-size:11px;" >
                                                            <strong style="color:#BF461E; font-size:12px; margin-bottom:5px">' . $product['name'] . '</strong>
                                                            <ul style="margin:0">';

						$price = self::to_number( $product['price'] );
						if ( is_array( rgar( $product, 'options' ) ) ) {
							foreach ( $product['options'] as $option ) {
								$price += self::to_number( $option['price'] );
								$field_data .= '<li style="padding:4px 0 4px 0">' . $option['option_label'] . '</li>';
							}
						}
						$subtotal = floatval( $product['quantity'] ) * $price;
						$total += $subtotal;

						$field_data .= '</ul>
                                                        </td>
                                                        <td style="border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; text-align:center; width:50px; font-family: sans-serif; font-size:11px;" >' . $product['quantity'] . '</td>
                                                        <td style="border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; width:155px; font-family: sans-serif; font-size:11px;" >' . self::to_money( $price, $lead['currency'] ) . '</td>
                                                        <td style="border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; width:155px; font-family: sans-serif; font-size:11px;" >' . self::to_money( $subtotal, $lead['currency'] ) . '</td>
                                                    </tr>';
					}
					$total += floatval( $products['shipping']['price'] );
					$field_data .= '</tbody>
                                <tfoot>';

					if ( ! empty( $products['shipping']['name'] ) ) {
						$field_data .= '
                                    <tr>
                                        <td colspan="2" rowspan="2" style="background-color:#F4F4F4; border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; font-size:11px;">&nbsp;</td>
                                        <td style="border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; text-align:right; width:155px; font-family: sans-serif;"><strong style="font-size:12px;">' . $products['shipping']['name'] . '</strong></td>
                                        <td style="border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; width:155px; font-family: sans-serif;"><strong style="font-size:12px;">' . self::to_money( $products['shipping']['price'], $lead['currency'] ) . '</strong></td>
                                    </tr>
                                    ';
					}

					$field_data .= '
                                    <tr>';

					if ( empty( $products['shipping']['name'] ) ) {
						$field_data .= '
                                        <td colspan="2" style="background-color:#F4F4F4; border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; font-size:11px;">&nbsp;</td>';
					}

					$field_data .= '
                                        <td style="border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; text-align:right; width:155px; font-family: sans-serif;"><strong style="font-size:12px;">' . esc_html__( 'Total:' , 'gravityforms' ) . '</strong></td>
                                        <td style="border-bottom:1px solid #DFDFDF; border-right:1px solid #DFDFDF; padding:7px; width:155px; font-family: sans-serif;"><strong style="font-size:12px;">' . self::to_money( $total, $lead['currency'] ) . '</strong></td>
                                    </tr>
                                </tfoot>
                               </table>
                            </td>
                        </tr>';
				}
				break;
		}

		return $field_data;
	}

	public static function send_user_notification( $form, $lead, $override_options = false ) {
		_deprecated_function( 'send_user_notification', '1.7', 'send_notification' );

		$notification = self::prepare_user_notification( $form, $lead, $override_options );
		self::send_email( $notification['from'], $notification['to'], $notification['bcc'], $notification['reply_to'], $notification['subject'], $notification['message'], $notification['from_name'], $notification['message_format'], $notification['attachments'] );
	}

	public static function send_admin_notification( $form, $lead, $override_options = false ) {
		_deprecated_function( 'send_admin_notification', '1.7', 'send_notification' );

		$notification = self::prepare_admin_notification( $form, $lead, $override_options );
		self::send_email( $notification['from'], $notification['to'], $notification['bcc'], $notification['replyTo'], $notification['subject'], $notification['message'], $notification['from_name'], $notification['message_format'], $notification['attachments'] );
	}

	private static function prepare_user_notification( $form, $lead, $override_options = false ) {
		$form_id = $form['id'];

		if ( ! isset( $form['autoResponder'] ) ) {
			return;
		}

		//handling autoresponder email
		$to_field = isset( $form['autoResponder']['toField'] ) ? rgget( $form['autoResponder']['toField'], $lead ) : '';
		$to       = gf_apply_filters( 'gform_autoresponder_email', $form_id, $to_field, $form );
		$subject  = GFCommon::replace_variables( rgget( 'subject', $form['autoResponder'] ), $form, $lead, false, false );

		$message_format = gf_apply_filters( 'gform_notification_format', $form_id, 'html', 'user', $form, $lead );
		$message        = GFCommon::replace_variables( rgget( 'message', $form['autoResponder'] ), $form, $lead, false, false, ! rgget( 'disableAutoformat', $form['autoResponder'] ), $message_format );

		if ( apply_filters( 'gform_enable_shortcode_notification_message', true, $form, $lead ) ) {
			$message = do_shortcode( $message );
		}

		//Running trough variable replacement
		$to        = GFCommon::replace_variables( $to, $form, $lead, false, false );
		$from      = GFCommon::replace_variables( rgget( 'from', $form['autoResponder'] ), $form, $lead, false, false );
		$bcc       = GFCommon::replace_variables( rgget( 'bcc', $form['autoResponder'] ), $form, $lead, false, false );
		$reply_to  = GFCommon::replace_variables( rgget( 'replyTo', $form['autoResponder'] ), $form, $lead, false, false );
		$from_name = GFCommon::replace_variables( rgget( 'fromName', $form['autoResponder'] ), $form, $lead, false, false );

		// override default values if override options provided
		if ( $override_options && is_array( $override_options ) ) {
			foreach ( $override_options as $override_key => $override_value ) {
				${$override_key} = $override_value;
			}
		}

		$attachments = gf_apply_filters( 'gform_user_notification_attachments', $form_id, array(), $lead, $form );

		//Disabling autoformat to prevent double autoformatting of messages
		$disableAutoformat = '1';

		return compact( 'to', 'from', 'bcc', 'reply_to', 'subject', 'message', 'from_name', 'message_format', 'attachments', 'disableAutoformat' );
	}

	private static function prepare_admin_notification( $form, $lead, $override_options = false ) {
		$form_id = $form['id'];

		//handling admin notification email
		$subject = GFCommon::replace_variables( rgget( 'subject', $form['notification'] ), $form, $lead, false, false );

		$message_format = gf_apply_filters( 'gform_notification_format', $form_id, 'html', 'admin', $form, $lead );
		$message        = GFCommon::replace_variables( rgget( 'message', $form['notification'] ), $form, $lead, false, false, ! rgget( 'disableAutoformat', $form['notification'] ), $message_format );

		if ( apply_filters( 'gform_enable_shortcode_notification_message', true, $form, $lead ) ) {
			$message = do_shortcode( $message );
		}

		$version_info = self::get_version_info();
		$is_expired   = ! rgempty( 'expiration_time', $version_info ) && $version_info['expiration_time'] < time();
		if ( ! rgar( $version_info, 'is_valid_key' ) && $is_expired ) {
			$message .= "<br/><br/>Your Gravity Forms License Key has expired. In order to continue receiving support and software updates you must renew your license key. You can do so by following the renewal instructions on the Gravity Forms Settings page in your WordPress Dashboard or by <a href='http://www.gravityhelp.com/renew-license/?key=" . self::get_key() . "'>clicking here</a>.";
		}

		$from = rgempty( 'fromField', $form['notification'] ) ? rgget( 'from', $form['notification'] ) : rgget( $form['notification']['fromField'], $lead );

		if ( rgempty( 'fromNameField', $form['notification'] ) ) {
			$from_name = rgget( 'fromName', $form['notification'] );
		} else {
			$field     = RGFormsModel::get_field( $form, rgget( 'fromNameField', $form['notification'] ) );
			$value     = RGFormsModel::get_lead_field_value( $lead, $field );
			$from_name = GFCommon::get_lead_field_display( $field, $value );
		}

		$replyTo = rgempty( 'replyToField', $form['notification'] ) ? rgget( 'replyTo', $form['notification'] ) : rgget( $form['notification']['replyToField'], $lead );

		if ( rgempty( 'routing', $form['notification'] ) ) {
			$email_to = rgempty( 'toField', $form['notification'] ) ? rgget( 'to', $form['notification'] ) : rgget( 'toField', $form['notification'] );
		} else {
			$email_to = array();
			foreach ( $form['notification']['routing'] as $routing ) {

				$source_field   = RGFormsModel::get_field( $form, $routing['fieldId'] );
				$field_value    = RGFormsModel::get_lead_field_value( $lead, $source_field );
				$is_value_match = RGFormsModel::is_value_match( $field_value, $routing['value'], $routing['operator'], $source_field, $routing, $form ) && ! RGFormsModel::is_field_hidden( $form, $source_field, array(), $lead );

				if ( $is_value_match ) {
					$email_to[] = $routing['email'];
				}
			}

			$email_to = join( ',', $email_to );
		}

		//Running through variable replacement
		$email_to  = GFCommon::replace_variables( $email_to, $form, $lead, false, false );
		$from      = GFCommon::replace_variables( $from, $form, $lead, false, false );
		$bcc       = GFCommon::replace_variables( rgget( 'bcc', $form['notification'] ), $form, $lead, false, false );
		$reply_to  = GFCommon::replace_variables( $replyTo, $form, $lead, false, false );
		$from_name = GFCommon::replace_variables( $from_name, $form, $lead, false, false );

		//Filters the admin notification email to address. Allows users to change email address before notification is sent
		$to = gf_apply_filters( 'gform_notification_email', $form_id, $email_to, $lead );

		// override default values if override options provided
		if ( $override_options && is_array( $override_options ) ) {
			foreach ( $override_options as $override_key => $override_value ) {
				${$override_key} = $override_value;
			}
		}

		$attachments = gf_apply_filters( 'gform_admin_notification_attachments', $form_id, array(), $lead, $form );

		//Disabling autoformat to prevent double autoformatting of messages
		$disableAutoformat = '1';

		return compact( 'to', 'from', 'bcc', 'replyTo', 'subject', 'message', 'from_name', 'message_format', 'attachments', 'disableAutoformat' );

	}

	public static function send_notification( $notification, $form, $lead ) {

		GFCommon::log_debug( "GFCommon::send_notification(): Starting to process notification (#{$notification['id']} - {$notification['name']})." );
		$notification = gf_apply_filters( 'gform_notification', $form['id'], $notification, $form, $lead );

		$to_field = '';
		if ( rgar( $notification, 'toType' ) == 'field' ) {
			$to_field = rgar( $notification, 'toField' );
			if ( rgempty( 'toField', $notification ) ) {
				$to_field = rgar( $notification, 'to' );
			}
		}

		$email_to = rgar( $notification, 'to' );
		//do routing logic if "to" field doesn't have a value (to support legacy notifications that will run routing prior to this method)
		if ( empty( $email_to ) && rgar( $notification, 'toType' ) == 'routing' ) {
			$email_to = array();
			foreach ( $notification['routing'] as $routing ) {
				GFCommon::log_debug( __METHOD__ . '(): Evaluating Routing - rule => ' . print_r( $routing, 1 ) );

				$source_field   = RGFormsModel::get_field( $form, $routing['fieldId'] );
				$field_value    = RGFormsModel::get_lead_field_value( $lead, $source_field );
				$is_value_match = RGFormsModel::is_value_match( $field_value, $routing['value'], $routing['operator'], $source_field, $routing, $form ) && ! RGFormsModel::is_field_hidden( $form, $source_field, array(), $lead );

				if ( $is_value_match ) {
					$email_to[] = $routing['email'];
				}

				GFCommon::log_debug( __METHOD__ . '(): Evaluating Routing - field value => ' . print_r( $field_value, 1 ) );
				$is_value_match = $is_value_match ? 'Yes' : 'No';
				GFCommon::log_debug( __METHOD__ . '(): Evaluating Routing - is value match? ' . $is_value_match );
			}

			$email_to = join( ',', $email_to );
		} else if ( ! empty( $to_field ) ) {
			$source_field = RGFormsModel::get_field( $form, $to_field );
			$email_to     = RGFormsModel::get_lead_field_value( $lead, $source_field );
		}

		//Running through variable replacement
		$to        = GFCommon::replace_variables( $email_to, $form, $lead, false, false );
		$subject   = GFCommon::replace_variables( rgar( $notification, 'subject' ), $form, $lead, false, false, true, 'text' );
		$from      = GFCommon::replace_variables( rgar( $notification, 'from' ), $form, $lead, false, false );
		$from_name = GFCommon::replace_variables( rgar( $notification, 'fromName' ), $form, $lead, false, false, true, 'text' );
		$bcc       = GFCommon::replace_variables( rgar( $notification, 'bcc' ), $form, $lead, false, false );
		$replyTo   = GFCommon::replace_variables( rgar( $notification, 'replyTo' ), $form, $lead, false, false );

		$message_format = rgempty( 'message_format', $notification ) ? 'html' : rgar( $notification, 'message_format' );
		$message        = GFCommon::replace_variables( rgar( $notification, 'message' ), $form, $lead, false, false, ! rgar( $notification, 'disableAutoformat' ), $message_format );

		if ( apply_filters( 'gform_enable_shortcode_notification_message', true, $form, $lead ) ) {
			$message = do_shortcode( $message );
		}

		// allow attachments to be passed as a single path (string) or an array of paths, if string provided, add to array
		$attachments = rgar( $notification, 'attachments' );
		if ( ! empty( $attachments ) ) {
			$attachments = is_array( $attachments ) ? $attachments : array( $attachments );
		} else {
			$attachments = array();
		}

		self::send_email( $from, $to, $bcc, $replyTo, $subject, $message, $from_name, $message_format, $attachments );

		return compact( 'to', 'from', 'bcc', 'replyTo', 'subject', 'message', 'from_name', 'message_format', 'attachments' );

	}

	public static function send_notifications( $notification_ids, $form, $lead, $do_conditional_logic = true, $event = 'form_submission' ) {
		$entry_id = rgar( $lead, 'id' );
		if ( ! is_array( $notification_ids ) || empty( $notification_ids ) ) {
			GFCommon::log_debug( "GFCommon::send_notifications(): Aborting. No notifications to process for {$event} event for entry #{$entry_id}." );

			return;
		}

		GFCommon::log_debug( "GFCommon::send_notifications(): Processing notifications for {$event} event for entry #{$entry_id}: " . print_r( $notification_ids, true ) . "\n(only active/applicable notifications are sent)" );

		foreach ( $notification_ids as $notification_id ) {
			if ( ! isset( $form['notifications'][ $notification_id ] ) ) {
				continue;
			}
			if ( isset( $form['notifications'][ $notification_id ]['isActive'] ) && ! $form['notifications'][ $notification_id ]['isActive'] ) {
				GFCommon::log_debug( "GFCommon::send_notifications(): Notification is inactive, not processing notification (#{$notification_id} - {$form['notifications'][$notification_id]['name']})." );
				continue;
			}

			$notification = $form['notifications'][ $notification_id ];

			//check conditional logic when appropriate
			if ( $do_conditional_logic && ! GFCommon::evaluate_conditional_logic( rgar( $notification, 'conditionalLogic' ), $form, $lead ) ) {
				GFCommon::log_debug( "GFCommon::send_notifications(): Notification conditional logic not met, not processing notification (#{$notification_id} - {$notification['name']})." );
				continue;
			}

			if ( rgar( $notification, 'type' ) == 'user' ) {

				//Getting user notification from legacy structure (for backwards compatibility)
				$legacy_notification = GFCommon::prepare_user_notification( $form, $lead );
				$notification        = self::merge_legacy_notification( $notification, $legacy_notification );
			} else if ( rgar( $notification, 'type' ) == 'admin' ) {

				//Getting admin notification from legacy structure (for backwards compatibility)
				$legacy_notification = GFCommon::prepare_admin_notification( $form, $lead );
				$notification        = self::merge_legacy_notification( $notification, $legacy_notification );
			}

			//sending notification
			self::send_notification( $notification, $form, $lead );
		}

	}

	public static function send_form_submission_notifications( $form, $lead ) {
		GFAPI::send_notifications( $form, $lead );
	}

	private static function merge_legacy_notification( $notification, $notification_data ) {

		$keys = array( 'to', 'from', 'bcc', 'replyTo', 'subject', 'message', 'from_name', 'message_format', 'attachments', 'disableAutoformat' );
		foreach ( $keys as $key ) {
			$notification[ $key ] = rgar( $notification_data, $key );
		}

		return $notification;
	}

	public static function get_notifications_to_send( $event, $form, $lead ) {
		$notifications         = self::get_notifications( $event, $form );
		$notifications_to_send = array();
		foreach ( $notifications as $notification ) {
			if ( GFCommon::evaluate_conditional_logic( rgar( $notification, 'conditionalLogic' ), $form, $lead ) ) {
				$notifications_to_send[] = $notification;
			}
		}

		return $notifications_to_send;
	}

	public static function get_notifications( $event, $form ) {
		if ( rgempty( 'notifications', $form ) ) {
			return array();
		}

		$notifications = array();
		foreach ( $form['notifications'] as $notification ) {
			$notification_event = rgar( $notification, 'event' );
			$omit_from_resend   = array( 'form_saved', 'form_save_email_requested' );
			if ( $notification_event == $event || ( $event == 'resend_notifications' && ! in_array( $notification_event, $omit_from_resend ) ) ) {
				$notifications[] = $notification;
			}
		}

		return $notifications;
	}

	public static function has_admin_notification( $form ) {

		return ( ! empty( $form['notification']['to'] ) || ! empty( $form['notification']['routing'] ) ) && ( ! empty( $form['notification']['subject'] ) || ! empty( $form['notification']['message'] ) );

	}

	public static function has_user_notification( $form ) {

		return ! empty( $form['autoResponder']['toField'] ) && ( ! empty( $form['autoResponder']['subject'] ) || ! empty( $form['autoResponder']['message'] ) );

	}

	private static function send_email( $from, $to, $bcc, $reply_to, $subject, $message, $from_name = '', $message_format = 'html', $attachments = '' ) {
		
		global $phpmailer;

		$to    = str_replace( ' ', '', $to );
		$bcc   = str_replace( ' ', '', $bcc );
		$error = false;

		if ( ! GFCommon::is_valid_email( $from ) ) {
			$from = get_bloginfo( 'admin_email' );
		}

		if ( ! GFCommon::is_valid_email_list( $to ) ) {
			$error = new WP_Error( 'invalid_to', 'Cannot send email because the TO address is invalid.' );
		} else if ( empty( $subject ) && empty( $message ) ) {
			$error = new WP_Error( 'missing_subject_and_message', 'Cannot send email because there is no SUBJECT and no MESSAGE.' );
		} else if ( ! GFCommon::is_valid_email( $from ) ) {
			$error = new WP_Error( 'invalid_from', 'Cannot send email because the FROM address is invalid.' );
		}

		if ( is_wp_error( $error ) ) {
			GFCommon::log_error( 'GFCommon::send_email(): ' . $error->get_error_message() );
			GFCommon::log_error( print_r( compact( 'to', 'subject', 'message' ), true ) );

			/**
			 * Fires when an email from Gravity Forms has failed to send
			 *
			 * @param string $error The Error message returned after the email fails to send
			 */
			do_action( 'gform_send_email_failed', $error, compact( 'from', 'to', 'bcc', 'reply_to', 'subject', 'message', 'from_name', 'message_format', 'attachments' ) );

			return;
		}

		$content_type = $message_format == 'html' ? 'text/html' : 'text/plain';
		$name         = empty( $from_name ) ? $from : $from_name;

		$headers         = array();
		$headers['From'] = "From: \"" . wp_strip_all_tags( $name, true ) . "\" <{$from}>";

		if ( GFCommon::is_valid_email_list( $reply_to ) ) {
			$headers['Reply-To'] = "Reply-To: {$reply_to}";
		}

		if ( GFCommon::is_valid_email_list( $bcc ) ) {
			$headers['Bcc'] = "Bcc: $bcc";
		}

		$headers['Content-type'] = "Content-type: {$content_type}; charset=" . get_option( 'blog_charset' );

		$abort_email = false;
		extract( apply_filters( 'gform_pre_send_email', compact( 'to', 'subject', 'message', 'headers', 'attachments', 'abort_email' ), $message_format ) );

		$is_success = false;
		if ( ! $abort_email ) {
			GFCommon::log_debug( 'GFCommon::send_email(): Sending email via wp_mail().' );
			GFCommon::log_debug( print_r( compact( 'to', 'subject', 'message', 'headers', 'attachments', 'abort_email' ), true ) );
			$is_success = wp_mail( $to, $subject, $message, $headers, $attachments );
			$result = is_wp_error( $is_success ) ? $is_success->get_error_message() : $is_success;
			GFCommon::log_debug( "GFCommon::send_email(): Result from wp_mail(): {$result}" );
			if ( ! is_wp_error( $is_success ) && $is_success ) {
				GFCommon::log_debug( 'GFCommon::send_email(): Mail was passed from WordPress to the mail server.' );
			} else {
				GFCommon::log_error( 'GFCommon::send_email(): The mail message was passed off to WordPress for processing, but WordPress was unable to send the message.' );
			}

			if ( has_filter( 'phpmailer_init' ) ) {
				GFCommon::log_debug( __METHOD__ . '(): The WordPress phpmailer_init hook has been detected, usually used by SMTP plugins, it can impact mail delivery.' );
			}

			if ( ! empty( $phpmailer->ErrorInfo ) ) {
				GFCommon::log_debug( __METHOD__ . '(): PHPMailer class returned an error message: ' . $phpmailer->ErrorInfo );
			}			
		} else {
			GFCommon::log_debug( 'GFCommon::send_email(): Aborting. The gform_pre_send_email hook was used to set the abort_email parameter to true.' );
		}

		self::add_emails_sent();


		do_action( 'gform_after_email', $is_success, $to, $subject, $message, $headers, $attachments, $message_format, $from, $from_name, $bcc, $reply_to );
	}

	public static function add_emails_sent() {

		$count = self::get_emails_sent();

		update_option( 'gform_email_count', ++$count );

	}

	public static function get_emails_sent() {
		$count = get_option( 'gform_email_count' );

		if ( ! $count ) {
			$count = 0;
		}

		return $count;
	}

	public static function get_api_calls() {
		$count = get_option( 'gform_api_count' );

		if ( ! $count ) {
			$count = 0;
		}

		return $count;
	}

	public static function add_api_call() {

		$count = self::get_api_calls();

		update_option( 'gform_api_count', ++$count );

	}

	public static function has_post_field( $fields ) {
		foreach ( $fields as $field ) {
			if ( in_array( $field->type, array( 'post_title', 'post_content', 'post_excerpt', 'post_category', 'post_image', 'post_tags', 'post_custom_field' ) ) ) {
				return true;
			}
		}

		return false;
	}

	public static function has_list_field( $form ) {
		return self::has_field_by_type( $form, 'list' );
	}

	public static function has_credit_card_field( $form ) {
		return self::has_field_by_type( $form, 'creditcard' );
	}

	private static function has_field_by_type( $form, $type ) {
		if ( is_array( $form['fields'] ) ) {
			foreach ( $form['fields'] as $field ) {

				if ( RGFormsModel::get_input_type( $field ) == $type ) {
					return true;
				}
			}
		}

		return false;
	}

	public static function current_user_can_any( $caps ) {

		if ( ! is_array( $caps ) ) {
			$has_cap = current_user_can( $caps ) || current_user_can( 'gform_full_access' );

			return $has_cap;
		}

		foreach ( $caps as $cap ) {
			if ( current_user_can( $cap ) ) {
				return true;
			}
		}

		$has_full_access = current_user_can( 'gform_full_access' );

		return $has_full_access;
	}

	public static function current_user_can_which( $caps ) {

		foreach ( $caps as $cap ) {
			if ( current_user_can( $cap ) ) {
				return $cap;
			}
		}

		return '';
	}

	public static function is_pricing_field( $field_type ) {
		return self::is_product_field( $field_type ) || $field_type == 'donation';
	}

	public static function is_product_field( $field_type ) {
		return in_array( $field_type, array( 'option', 'quantity', 'product', 'total', 'shipping', 'calculation' ) );
	}

	public static function all_caps() {
		return array(
			'gravityforms_edit_forms',
			'gravityforms_delete_forms',
			'gravityforms_create_form',
			'gravityforms_view_entries',
			'gravityforms_edit_entries',
			'gravityforms_delete_entries',
			'gravityforms_view_settings',
			'gravityforms_edit_settings',
			'gravityforms_export_entries',
			'gravityforms_uninstall',
			'gravityforms_view_entry_notes',
			'gravityforms_edit_entry_notes',
			'gravityforms_view_updates',
			'gravityforms_view_addons',
			'gravityforms_preview_forms',
		);
	}

	public static function delete_directory( $dir ) {
		if ( ! file_exists( $dir ) ) {
			return;
		}

		if ( $handle = opendir( $dir ) ) {
			$array = array();
			while ( false !== ( $file = readdir( $handle ) ) ) {
				if ( $file != '.' && $file != '..' ) {
					if ( is_dir( $dir . $file ) ) {
						if ( ! @rmdir( $dir . $file ) ) {
							// Empty directory? Remove it
							self::delete_directory( $dir . $file . '/' );
						} // Not empty? Delete the files inside it
					} else {
						@unlink( $dir . $file );
					}
				}
			}
			closedir( $handle );
			@rmdir( $dir );
		}
	}

	public static function get_remote_message() {
		return stripslashes( get_option( 'rg_gforms_message' ) );
	}

	public static function get_key() {
		return get_option( 'rg_gforms_key' );
	}

	public static function has_update( $use_cache = true ) {
		$version_info = GFCommon::get_version_info( $use_cache );
		$version      = rgar( $version_info, 'version' );

		return empty( $version ) ? false : version_compare( GFCommon::$version, $version, '<' );
	}

	public static function get_key_info( $key ) {

		$options            = array( 'method' => 'POST', 'timeout' => 3 );
		$options['headers'] = array(
			'Content-Type' => 'application/x-www-form-urlencoded; charset=' . get_option( 'blog_charset' ),
			'User-Agent'   => 'WordPress/' . get_bloginfo( 'version' ),
			'Referer'      => get_bloginfo( 'url' )
		);

		$raw_response = self::post_to_manager( 'api.php', "op=get_key&key={$key}", $options );

		if ( is_wp_error( $raw_response ) || $raw_response['response']['code'] != 200 ) {
			return array();
		}

		$key_info = unserialize( trim( $raw_response['body'] ) );

		return $key_info ? $key_info : array();
	}

	public static function get_version_info( $cache = true ) {

		$raw_response = get_transient( 'gform_update_info' );
		if ( ! $cache ) {
			$raw_response = null;
		}

		if ( ! $raw_response ) {
			//Getting version number
			$options            = array( 'method' => 'POST', 'timeout' => 20 );
			$options['headers'] = array(
				'Content-Type' => 'application/x-www-form-urlencoded; charset=' . get_option( 'blog_charset' ),
				'User-Agent'   => 'WordPress/' . get_bloginfo( 'version' ),
				'Referer'      => get_bloginfo( 'url' )
			);
			$options['body']    = self::get_remote_post_params();
			$options['timeout'] = 15;

			$nocache = $cache ? '' : 'nocache=1'; //disabling server side caching

			$raw_response = self::post_to_manager( 'version.php', $nocache, $options );

			//caching responses.
			set_transient( 'gform_update_info', $raw_response, 86400 ); //caching for 24 hours
		}

		if ( is_wp_error( $raw_response ) || rgars( $raw_response, 'response/code' ) != 200 ) {

			return array( 'is_valid_key' => '1', 'version' => '', 'url' => '', 'is_error' => '1' );
		}

		$version_info = json_decode( $raw_response['body'], true );

		if ( empty( $version_info ) ) {
			return array( 'is_valid_key' => '1', 'version' => '', 'url' => '', 'is_error' => '1' );
		}

		return $version_info;
	}

	public static function get_remote_request_params() {
		global $wpdb;

		return sprintf( 'of=GravityForms&key=%s&v=%s&wp=%s&php=%s&mysql=%s&version=2', urlencode( self::get_key() ), urlencode( self::$version ), urlencode( get_bloginfo( 'version' ) ), urlencode( phpversion() ), urlencode( $wpdb->db_version() ) );
	}

	public static function get_remote_post_params() {
		global $wpdb;

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
		$plugin_list = get_plugins();
		$site_url    = get_bloginfo( 'url' );
		$plugins     = array();

		$active_plugins = get_option( 'active_plugins' );

		foreach ( $plugin_list as $key => $plugin ) {
			$is_active = in_array( $key, $active_plugins );

			//filter for only gravityforms ones, may get some others if using our naming convention
			if ( strpos( strtolower( $plugin['Title'] ), 'gravity forms' ) !== false ) {
				$name      = substr( $key, 0, strpos( $key, '/' ) );
				$plugins[] = array( 'name' => $name, 'version' => $plugin['Version'], 'is_active' => $is_active );
			}
		}
		$plugins = json_encode( $plugins );

		//get theme info
		$theme            = wp_get_theme();
		$theme_name       = $theme->get( 'Name' );
		$theme_uri        = $theme->get( 'ThemeURI' );
		$theme_version    = $theme->get( 'Version' );
		$theme_author     = $theme->get( 'Author' );
		$theme_author_uri = $theme->get( 'AuthorURI' );

		$form_counts    = GFFormsModel::get_form_count();
		$active_count   = $form_counts['active'];
		$inactive_count = $form_counts['inactive'];
		$fc             = abs( $active_count ) + abs( $inactive_count );
		$entry_count    = GFFormsModel::get_lead_count_all_forms( 'active' );
		$im             = is_multisite();

		$post = array( 'of' => 'gravityforms', 'key' => self::get_key(), 'v' => self::$version, 'wp' => get_bloginfo( 'version' ), 'php' => phpversion(), 'mysql' => $wpdb->db_version(), 'version' => '2', 'plugins' => $plugins, 'tn' => $theme_name, 'tu' => $theme_uri, 'tv' => $theme_version, 'ta' => $theme_author, 'tau' => $theme_author_uri, 'im' => $im, 'fc' => $fc, 'ec' => $entry_count, 'emc' => self::get_emails_sent(), 'api' => self::get_api_calls() );

		return $post;
	}

	public static function ensure_wp_version() {
		if ( ! GF_SUPPORTED_WP_VERSION ) {
			echo "<div class='error' style='padding:10px;'>" . sprintf( esc_html__( 'Gravity Forms require WordPress %s or greater. You must upgrade WordPress in order to use Gravity Forms' , 'gravityforms' ), GF_MIN_WP_VERSION ) . '</div>';

			return false;
		}

		return true;
	}

	public static function check_update( $option, $cache = true ) {

		if ( ! is_object( $option ) ) {
			return $option;
		}

		$version_info = self::get_version_info( $cache );

		if ( ! $version_info ) {
			return $option;
		}

		$plugin_path = 'gravityforms/gravityforms.php';
		if ( empty( $option->response[ $plugin_path ] ) ) {
			$option->response[ $plugin_path ] = new stdClass();
		}

		$version = rgar( $version_info, 'version' );
		//Empty response means that the key is invalid. Do not queue for upgrade
		if ( ! rgar( $version_info, 'is_valid_key' ) || version_compare( GFCommon::$version, $version, '>=' ) ) {
			unset( $option->response[ $plugin_path ] );
		} else {
			$url                                         = rgar( $version_info, 'url' );
			$option->response[ $plugin_path ]->url         = 'http://www.gravityforms.com';
			$option->response[ $plugin_path ]->slug        = 'gravityforms';
			$option->response[ $plugin_path ]->plugin      = $plugin_path;
			$option->response[ $plugin_path ]->package     = str_replace( '{KEY}', GFCommon::get_key(), $url );
			$option->response[ $plugin_path ]->new_version = $version;
			$option->response[ $plugin_path ]->id          = '0';
		}

		return $option;

	}

	public static function cache_remote_message() {
		//Getting version number
		$key                = GFCommon::get_key();
		$body               = "key=$key";
		$options            = array( 'method' => 'POST', 'timeout' => 3, 'body' => $body );
		$options['headers'] = array(
			'Content-Type'   => 'application/x-www-form-urlencoded; charset=' . get_option( 'blog_charset' ),
			'Content-Length' => strlen( $body ),
			'User-Agent'     => 'WordPress/' . get_bloginfo( 'version' ),
			'Referer'        => get_bloginfo( 'url' )
		);

		$raw_response = self::post_to_manager( 'message.php', GFCommon::get_remote_request_params(), $options );

		if ( is_wp_error( $raw_response ) || 200 != $raw_response['response']['code'] ) {
			$message = '';
		} else {
			$message = $raw_response['body'];
		}

		//validating that message is a valid Gravity Form message. If message is invalid, don't display anything
		if ( substr( $message, 0, 10 ) != '<!--GFM-->' ) {
			$message = '';
		}

		update_option( 'rg_gforms_message', $message );
	}

	public static function post_to_manager( $file, $query, $options ){

		$request_url = GRAVITY_MANAGER_URL . '/' . $file . '?' . $query;
		self::log_debug( 'Posting to manager: ' . $request_url );
		$raw_response = wp_remote_post( $request_url, $options );
		self::log_debug( print_r( $raw_response, true ) );

		if ( is_wp_error( $raw_response ) || 200 != $raw_response['response']['code'] ){
			self::log_error( 'Error from manager. Sending to proxy...' );
			$request_url = GRAVITY_MANAGER_PROXY_URL . '/proxy.php?f=' . $file . '&' . $query;
			$raw_response = wp_remote_post( $request_url, $options );
			self::log_debug( print_r( $raw_response, true ) );
		}

		return $raw_response;
	}

	public static function get_local_timestamp( $timestamp = null ) {
		if ( $timestamp == null ) {
			$timestamp = time();
		}

		return $timestamp + ( get_option( 'gmt_offset' ) * 3600 );
	}

	public static function get_gmt_timestamp( $local_timestamp ) {
		return $local_timestamp - ( get_option( 'gmt_offset' ) * 3600 );
	}

	public static function format_date( $gmt_datetime, $is_human = true, $date_format = '', $include_time = true ) {
		if ( empty( $gmt_datetime ) ) {
			return '';
		}

		//adjusting date to local configured Time Zone
		$lead_gmt_time   = mysql2date( 'G', $gmt_datetime );
		$lead_local_time = self::get_local_timestamp( $lead_gmt_time );

		if ( empty( $date_format ) ) {
			$date_format = get_option( 'date_format' );
		}

		if ( $is_human ) {
			$time_diff = time() - $lead_gmt_time;

			if ( $time_diff > 0 && $time_diff < 24 * 60 * 60 ) {
				$date_display = sprintf( esc_html__( '%s ago', 'gravityforms' ), human_time_diff( $lead_gmt_time ) );
			} else {
				$date_display = $include_time ? sprintf( esc_html__( '%1$s at %2$s', 'gravityforms' ), date_i18n( $date_format, $lead_local_time, true ), date_i18n( get_option( 'time_format' ), $lead_local_time, true ) ) : date_i18n( $date_format, $lead_local_time, true );
			}
		} else {
			$date_display = $include_time ? sprintf( esc_html__( '%1$s at %2$s', 'gravityforms' ), date_i18n( $date_format, $lead_local_time, true ), date_i18n( get_option( 'time_format' ), $lead_local_time, true ) ) : date_i18n( $date_format, $lead_local_time, true );
		}

		return $date_display;
	}

	public static function get_selection_value( $value ) {
		$ary = explode( '|', $value );
		$val = $ary[0];

		return $val;
	}

	public static function selection_display( $value, $field, $currency = '', $use_text = false ) {
		if ( is_array( $value ) ) {
			return '';
		}

		if ( $field !== null && $field->enablePrice ) {
			$ary   = explode( '|', $value );
			$val   = $ary[0];
			$price = count( $ary ) > 1 ? $ary[1] : '';
		} else {
			$val = $value;
			$price = '';
		}

		if ( $use_text ) {
			$val = RGFormsModel::get_choice_text( $field, $val );
		}

		if ( ! empty( $price ) ) {
			return "$val (" . self::to_money( $price, $currency ) . ')';
		} else {
			return $val;
		}
	}

	public static function date_display( $value, $input_format = 'mdy', $output_format = false ) {

		if ( ! $output_format ) {
			$output_format = $input_format;
		}

		$date = self::parse_date( $value, $input_format );
		if ( empty( $date ) ) {
			return $value;
		}

		list( $position, $separator ) = rgexplode( '_', $output_format, 2 );
		switch ( $separator ) {
			case 'dash' :
				$separator = '-';
				break;
			case 'dot' :
				$separator = '.';
				break;
			default :
				$separator = '/';
				break;
		}

		switch ( $position ) {
			case 'year' :
			case 'month' :
			case 'day' :
				return $date[ $position ];

			case 'ymd' :
				return $date['year'] . $separator . $date['month'] . $separator . $date['day'];
				break;

			case 'dmy' :
				return $date['day'] . $separator . $date['month'] . $separator . $date['year'];
				break;

			default :
				return $date['month'] . $separator . $date['day'] . $separator . $date['year'];
				break;

		}
	}

	public static function parse_date( $date, $format = 'mdy' ) {
		$date_info = array();

		$position = substr( $format, 0, 3 );

		if ( is_array( $date ) ) {

			switch ( $position ) {
				case 'mdy' :
					$date_info['month'] = rgar( $date, 0 );
					$date_info['day']   = rgar( $date, 1 );
					$date_info['year']  = rgar( $date, 2 );
					break;

				case 'dmy' :
					$date_info['day']   = rgar( $date, 0 );
					$date_info['month'] = rgar( $date, 1 );
					$date_info['year']  = rgar( $date, 2 );
					break;

				case 'ymd' :
					$date_info['year']  = rgar( $date, 0 );
					$date_info['month'] = rgar( $date, 1 );
					$date_info['day']   = rgar( $date, 2 );
					break;
			}
			return $date_info;
		}

		$date = preg_replace( "|[/\.]|", '-', $date );
		if ( preg_match( '/^(\d{1,4})-(\d{1,2})-(\d{1,4})$/', $date, $matches ) ) {

			if ( strlen( $matches[1] ) == 4 ) {
				//format yyyy-mm-dd
				$date_info['year']  = $matches[1];
				$date_info['month'] = $matches[2];
				$date_info['day']   = $matches[3];
			} else if ( $position == 'mdy' ) {
				//format mm-dd-yyyy
				$date_info['month'] = $matches[1];
				$date_info['day']   = $matches[2];
				$date_info['year']  = $matches[3];
			} else {
				//format dd-mm-yyyy
				$date_info['day']   = $matches[1];
				$date_info['month'] = $matches[2];
				$date_info['year']  = $matches[3];
			}
		}

		return $date_info;
	}


	public static function truncate_url( $url ) {
		$truncated_url = basename( $url );
		if ( empty( $truncated_url ) ) {
			$truncated_url = dirname( $url );
		}

		$ary = explode( '?', $truncated_url );

		return $ary[0];
	}

	public static function get_field_placeholder_attribute( $field ) {

		$placeholder_value = GFCommon::replace_variables_prepopulate( $field->placeholder );

		return ! empty( $placeholder_value ) ? sprintf( "placeholder='%s'", esc_attr( $placeholder_value ) ) : '';
	}

	public static function get_input_placeholder_attribute( $input ) {

		$placeholder_value = self::get_input_placeholder_value( $input );

		return ! empty( $placeholder_value ) ? sprintf( "placeholder='%s'", esc_attr( $placeholder_value ) ) : '';
	}

	public static function get_input_placeholder_value( $input ) {

		$placeholder = rgar( $input, 'placeholder' );

		return empty( $placeholder ) ? '' : GFCommon::replace_variables_prepopulate( $placeholder );
	}

	public static function get_tabindex() {
		return GFCommon::$tab_index > 0 ? "tabindex='" . GFCommon::$tab_index ++ . "'" : '';
	}

	/**
	 * @deprecated
	 *
	 * @param GF_Field_Checkbox $field
	 * @param                   $value
	 * @param                   $disabled_text
	 *
	 * @return mixed
	 */
	public static function get_checkbox_choices( $field, $value, $disabled_text ) {
		_deprecated_function( 'get_checkbox_choices', '1.9', 'GF_Field_Checkbox::get_checkbox_choices' );

		return $field->get_checkbox_choices( $value, $disabled_text );
	}

	/**
	 * @deprecated Deprecated since 1.9. Use GF_Field_Checkbox::get_radio_choices() instead.
	 *
	 * @param GF_Field_Radio $field
	 * @param string         $value
	 * @param                $disabled_text
	 *
	 * @return mixed
	 */
	public static function get_radio_choices( $field, $value = '', $disabled_text ) {
		_deprecated_function( 'get_radio_choices', '1.9', 'GF_Field_Checkbox::get_radio_choices' );

		return $field->get_radio_choices( $value, $disabled_text );
	}

	public static function get_field_type_title( $type ) {
		$gf_field = GF_Fields::get( $type );
		if ( ! empty( $gf_field ) ) {
			return $gf_field->get_form_editor_field_title();
		}

		return apply_filters( 'gform_field_type_title', $type, $type );
	}

	public static function get_select_choices( $field, $value = '' ) {
		$choices = '';

		if ( RG_CURRENT_VIEW == 'entry' && empty( $value ) && empty( $field->placeholder ) ) {
			$choices .= "<option value=''></option>";
		}

		if ( is_array( $field->choices ) ) {

			if ( GFFormsModel::get_input_type( $field ) == 'select' && ! empty( $field->placeholder ) ) {
				$selected = empty( $value ) ? "selected='selected'" : '';
				$choices .= sprintf( "<option value='' %s class='gf_placeholder'>%s</option>", $selected, esc_html( $field->placeholder ) );
			}

			foreach ( $field->choices as $choice ) {

				//needed for users upgrading from 1.0
				$field_value = ! empty( $choice['value'] ) || $field->enableChoiceValue || $field->type == 'post_category' ? $choice['value'] : $choice['text'];
				if ( $field->enablePrice ) {
					$price = rgempty( 'price', $choice ) ? 0 : GFCommon::to_number( rgar( $choice, 'price' ) );
					$field_value .= '|' . $price;
				}

				if ( ! isset( $_GET['gf_token'] ) && empty( $_POST ) && rgblank( $value ) && RG_CURRENT_VIEW != 'entry' ) {
					$selected = rgar( $choice, 'isSelected' ) ? "selected='selected'" : '';
				} else {
					if ( is_array( $value ) ) {
						$is_match = false;
						foreach ( $value as $item ) {
							if ( RGFormsModel::choice_value_match( $field, $choice, $item ) ) {
								$is_match = true;
								break;
							}
						}
						$selected = $is_match ? "selected='selected'" : '';
					} else {
						$selected = RGFormsModel::choice_value_match( $field, $choice, $value ) ? "selected='selected'" : '';
					}
				}

				$choice_markup = sprintf( "<option value='%s' %s>%s</option>", esc_attr( $field_value ), $selected, esc_html( $choice['text'] ) );

				$choices .= gf_apply_filters( 'gform_field_choice_markup_pre_render', array(
					$field->formId,
					$field->id
				), $choice_markup, $choice, $field, $value );

			}
		}

		return $choices;
	}

	public static function is_section_empty( $section_field, $form, $entry ) {

		$cache_key = "GFCommon::is_section_empty_{$form['id']}_{$section_field['id']}";
		$value     = GFCache::get( $cache_key );

		if ( $value !== false ) {
			return $value == true;
		}

		$fields = self::get_section_fields( $form, $section_field['id'] );
		if ( ! is_array( $fields ) ) {
			GFCache::set( $cache_key, 1 );

			return true;
		}

		foreach ( $fields as $field ) {

			$value = GFFormsModel::get_lead_field_value( $entry, $field );
			$value = GFCommon::get_lead_field_display( $field, $value, rgar( $entry, 'currency' ) );

			if ( rgblank( $value ) ) {
				continue;
			}

			// most fields are displayed in the section by default, exceptions are handled below
			$is_field_displayed_in_section = true;

			// by default, product fields are not displayed in their containing section (displayed in a product summary table)
			// if the filter is used to disable this, product fields are displayed in the section like other fields
			if ( self::is_product_field( $field['type'] ) ) {

				/**
				 * By default, product fields are not displayed in their containing section (displayed in a product summary table). If the filter is used to disable this, product fields are displayed in the section like other fields
				 *
				 * @param array $field The Form Fields Object
				 * @param array $form The Form Object
				 * @param array $entry The Entry object
				 *
				 */
				$display_product_summary = apply_filters( 'gform_display_product_summary', true, $field, $form, $entry );

				$is_field_displayed_in_section = ! $display_product_summary;
			}

			if ( $is_field_displayed_in_section ) {
				GFCache::set( $cache_key, 0 );

				return false;
			}
		}

		GFCache::set( $cache_key, 1 );

		return true;
	}

	public static function get_section_fields( $form, $section_field_id ) {
		$fields     = array();
		$in_section = false;
		foreach ( $form['fields'] as $field ) {
			if ( in_array( $field->type, array( 'section', 'page' ) ) && $in_section ) {
				return $fields;
			}

			if ( $field->id == $section_field_id ) {
				$in_section = true;
			}

			if ( $in_section ) {
				$fields[] = $field;
			}
		}

		return $fields;
	}

	public static function get_us_state_code( $state_name ) {
		return GF_Fields::get( 'address' )->get_us_state_code( $state_name );
	}

	public static function get_country_code( $country_name ) {
		return GF_Fields::get( 'address' )->get_country_code( $country_name );
	}

	public static function get_us_states() {
		return GF_Fields::get( 'address' )->get_us_states();
	}

	public static function get_canadian_provinces() {
		return GF_Fields::get( 'address' )->get_canadian_provinces();
	}

	public static function is_post_field( $field ) {
		return in_array( $field->type, array( 'post_title', 'post_tags', 'post_category', 'post_custom_field', 'post_content', 'post_excerpt', 'post_image' ) );
	}

	public static function get_fields_by_type( $form, $types ) {
		return GFAPI::get_fields_by_type( $form, $types );
	}

	public static function has_pages( $form ) {
		return sizeof( GFAPI::get_fields_by_type( $form, array( 'page' ) ) ) > 0;
	}

	public static function get_product_fields_by_type( $form, $types, $product_id ) {
		global $_product_fields;
		$key = json_encode( $types ) . '_' . $product_id . '_' . $form['id'];
		if ( ! isset( $_product_fields[ $key ] ) ) {
			$fields = array();
			for ( $i = 0, $count = sizeof( $form['fields'] ); $i < $count; $i ++ ) {
				$field = $form['fields'][ $i ];
				if ( in_array( $field->type, $types ) && $field->productField == $product_id ) {
					$fields[] = $field;
				}
			}
			$_product_fields[ $key ] = $fields;
		}

		return $_product_fields[ $key ];
	}

	/**
	 * @deprecated
	 *
	 * @param GF_Field $field
	 *
	 * @return mixed
	 */
	public static function has_field_calculation( $field ) {
		_deprecated_function( 'has_field_calculation', '1.7', 'GF_Field::has_calculation' );

		return $field->has_calculation();
	}

	/**
	 * @param GF_Field $field
	 * @param string   $value
	 * @param int      $lead_id
	 * @param int      $form_id
	 * @param null     $form
	 *
	 * @return mixed|string|void
	 */
	public static function get_field_input( $field, $value = '', $lead_id = 0, $form_id = 0, $form = null ) {

		if ( ! $field instanceof GF_Field ) {
			$field = GF_Fields::create( $field );
		}

		$is_form_editor = GFCommon::is_form_editor();
		$is_entry_detail = GFCommon::is_entry_detail();
		$is_admin = $is_form_editor || $is_entry_detail;

		$id       = intval( $field->id );
		$field_id = $is_admin || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";
		$form_id  = $is_admin && empty( $form_id ) ? rgget( 'id' ) : $form_id;

		if ( RG_CURRENT_VIEW == 'entry' ) {
			$lead      = RGFormsModel::get_lead( $lead_id );
			$post_id   = $lead['post_id'];
			$post_link = '';
			if ( is_numeric( $post_id ) && self::is_post_field( $field ) ) {
				$post_link = "<div>You can <a href='post.php?action=edit&post=$post_id'>edit this post</a> from the post page.</div>";
			}
		}

		$field_input = apply_filters( 'gform_field_input', '', $field, $value, $lead_id, $form_id );
		if ( $field_input ) {
			return $field_input;
		}

		//product fields are not editable
		if ( RG_CURRENT_VIEW == 'entry' && self::is_product_field( $field->type ) ) {
			return "<div class='ginput_container'>" . esc_html__( 'Product fields are not editable' , 'gravityforms' ) . '</div>';
		} else if ( RG_CURRENT_VIEW == 'entry' && $field->type == 'donation' ) {
			return "<div class='ginput_container'>" . esc_html__( 'Donations are not editable' , 'gravityforms' ) . '</div>';
		}

		// add categories as choices for Post Category field
		if ( $field->type == 'post_category' ) {
			$field = self::add_categories_as_choices( $field, $value );
		}

		$type = RGFormsModel::get_input_type( $field );
		switch ( $type ) {

			case 'honeypot':
				$autocomplete = RGFormsModel::is_html5_enabled() ? "autocomplete='off'" : '';

				return "<div class='ginput_container'><input name='input_{$id}' id='{$field_id}' type='text' value='' {$autocomplete}/></div>";
				break;

			case 'adminonly_hidden' :
				if ( ! is_array( $field->inputs ) ) {
					if ( is_array( $value ) ) {
						$value = json_encode( $value );
					}

					return sprintf( "<input name='input_%d' id='%s' class='gform_hidden' type='hidden' value='%s'/>", $id, esc_attr( $field_id ), esc_attr( $value ) );
				}


				$fields = '';
				foreach ( $field->inputs as $input ) {
					$fields .= sprintf( "<input name='input_%s' class='gform_hidden' type='hidden' value='%s'/>", $input['id'], esc_attr( rgar( $value, strval( $input['id'] ) ) ) );
				}

				return $fields;
				break;

			default :

				if ( ! empty( $post_link ) ) {
					return $post_link;
				}

				if ( ! isset( $lead ) ) {
					$lead = null;
				}

				return $field->get_field_input( $form, $value, $lead );

				break;

		}
	}

	public static function is_ssl() {
		global $wordpress_https;
		$is_ssl = false;

		$has_https_plugin  = class_exists( 'WordPressHTTPS' ) && isset( $wordpress_https );
		$has_is_ssl_method = $has_https_plugin && method_exists( 'WordPressHTTPS', 'is_ssl' );
		$has_isSsl_method  = $has_https_plugin && method_exists( 'WordPressHTTPS', 'isSsl' );

		//Use the WordPress HTTPs plugin if installed
		if ( $has_https_plugin && $has_is_ssl_method ) {
			$is_ssl = $wordpress_https->is_ssl();
		} else if ( $has_https_plugin && $has_isSsl_method ) {
			$is_ssl = $wordpress_https->isSsl();
		} else {
			$is_ssl = is_ssl();
		}


		if ( ! $is_ssl && isset( $_SERVER['HTTP_CF_VISITOR'] ) && strpos( $_SERVER['HTTP_CF_VISITOR'], 'https' ) ) {
			$is_ssl = true;
		}

		return apply_filters( 'gform_is_ssl', $is_ssl );
	}

	public static function is_preview() {
		$url_info  = parse_url( RGFormsModel::get_current_page_url() );
		$file_name = basename( $url_info['path'] );

		return $file_name == 'preview.php' || rgget( 'gf_page', $_GET ) == 'preview';
	}

	public static function clean_extensions( $extensions ) {
		$count = sizeof( $extensions );
		for ( $i = 0; $i < $count; $i ++ ) {
			$extensions[ $i ] = str_replace( '.', '', str_replace( ' ', '', $extensions[ $i ] ) );
		}

		return $extensions;
	}

	public static function get_disallowed_file_extensions() {

		$extensions = array( 'php', 'asp', 'aspx', 'cmd', 'csh', 'bat', 'html', 'hta', 'jar', 'exe', 'com', 'js', 'lnk', 'htaccess', 'phtml', 'ps1', 'ps2', 'php3', 'php4', 'php5', 'php6', 'py', 'rb', 'tmp' );

		// Intended for internal use - not to be included in the documentation.
		$extensions = apply_filters( 'gform_disallowed_file_extensions', $extensions );

		return $extensions;
	}

	public static function match_file_extension( $file_name, $extensions ) {
		if ( empty ( $extensions ) || ! is_array( $extensions ) ) {
			return false;
		}

		$ext = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
		if ( in_array( $ext, $extensions ) ) {
			return true;
		}

		return false;
	}

	public static function file_name_has_disallowed_extension( $file_name ) {

		return self::match_file_extension( $file_name, self::get_disallowed_file_extensions() ) || strpos( strtolower( $file_name ), '.php.' ) !== false;
	}

	public static function check_type_and_ext( $file, $file_name = ''){
		if ( empty( $file_name ) ) {
			$file_name = $file['name'];
		}
		$tmp_name = $file['tmp_name'];
		// Whitelist the mime type and extension
		$wp_filetype = wp_check_filetype_and_ext( $tmp_name, $file_name );
		$ext = empty( $wp_filetype['ext'] ) ? '' : $wp_filetype['ext'];
		$type = empty( $wp_filetype['type'] ) ? '' : $wp_filetype['type'];
		$proper_filename = empty( $wp_filetype['proper_filename'] ) ? '' : $wp_filetype['proper_filename'];

		if ( $proper_filename ) {
			return new WP_Error( 'invalid_file', esc_html__( 'There was an problem while verifying your file.' ) );
		}
		if ( ! $ext ) {
			return new WP_Error( 'illegal_extension', esc_html__( 'Sorry, this file extension is not permitted for security reasons.' ) );
		}
		if ( ! $type ) {
			return new WP_Error( 'illegal_type', esc_html__( 'Sorry, this file type is not permitted for security reasons.' ) );
		}

		return true;
	}

	public static function to_money( $number, $currency_code = '' ) {
		if ( ! class_exists( 'RGCurrency' ) ) {
			require_once( 'currency.php' );
		}

		if ( empty( $currency_code ) ) {
			$currency_code = self::get_currency();
		}

		$currency = new RGCurrency( $currency_code );

		return $currency->to_money( $number );
	}

	public static function to_number( $text, $currency_code = '' ) {
		if ( ! class_exists( 'RGCurrency' ) ) {
			require_once( 'currency.php' );
		}

		if ( empty( $currency_code ) ) {
			$currency_code = self::get_currency();
		}

		$currency = new RGCurrency( $currency_code );

		return $currency->to_number( $text );
	}

	public static function get_currency() {
		$currency = get_option( 'rg_gforms_currency' );
		$currency = empty( $currency ) ? 'USD' : $currency;

		return apply_filters( 'gform_currency', $currency );
	}

	public static function get_simple_captcha() {
		_deprecated_function( 'GFCommon::get_simple_captcha', '1.9', 'GFField_CAPTCHA::get_simple_captcha' );
		$captcha          = new ReallySimpleCaptcha();
		$captcha->tmp_dir = RGFormsModel::get_upload_path( 'captcha' ) . '/';

		return $captcha;
	}

	/**
	 * @deprecated
	 *
	 * @param GF_Field_CAPTCH $field
	 *
	 * @return mixed
	 */
	public static function get_captcha( $field ) {
		_deprecated_function( 'GFCommon::get_captcha', '1.9', 'GFField_CAPTCHA::get_captcha' );

		return $field->get_captcha();
	}

	/**
	 * @deprecated
	 *
	 * @param $field
	 * @param $pos
	 *
	 * @return mixed
	 */
	public static function get_math_captcha( $field, $pos ) {
		_deprecated_function( 'GFCommon::get_math_captcha', '1.9', 'GFField_CAPTCHA::get_math_captcha' );

		return $field->get_math_captcha( $pos );
	}

	/**
	 * @param GF_Field $field
	 * @param          $value
	 * @param string   $currency
	 * @param bool     $use_text
	 * @param string   $format
	 * @param string   $media
	 *
	 * @return array|mixed|string
	 */
	public static function get_lead_field_display( $field, $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {

		if ( ! $field instanceof GF_Field ) {
			$field = GF_Fields::create( $field );
		}

		if ( $field->type == 'post_category' ) {
			$value = self::prepare_post_category_value( $value, $field );
		}

		return $field->get_value_entry_detail( $value, $currency, $use_text, $format, $media );
	}

	public static function get_product_fields( $form, $lead, $use_choice_text = false, $use_admin_label = false ) {
		$products = array();

		$product_info = null;
		// retrieve static copy of product info (only for 'real' entries)
		if ( ! rgempty( 'id', $lead ) ) {
			$product_info = gform_get_meta( rgar( $lead, 'id' ), "gform_product_info_{$use_choice_text}_{$use_admin_label}" );
		}

		// if no static copy, generate from form/lead info
		if ( ! $product_info ) {

			foreach ( $form['fields'] as $field ) {
				$id         = $field->id;
				$lead_value = RGFormsModel::get_lead_field_value( $lead, $field );

				$quantity_field = self::get_product_fields_by_type( $form, array( 'quantity' ), $id );
				$quantity       = sizeof( $quantity_field ) > 0 && ! RGFormsModel::is_field_hidden( $form, $quantity_field[0], array(), $lead ) ? RGFormsModel::get_lead_field_value( $lead, $quantity_field[0] ) : 1;

				switch ( $field->type ) {

					case 'product' :

						//ignore products that have been hidden by conditional logic
						$is_hidden = RGFormsModel::is_field_hidden( $form, $field, array(), $lead );
						if ( $is_hidden ) {
							continue;
						}

						//if single product, get values from the multiple inputs
						if ( is_array( $lead_value ) ) {
							$product_quantity = sizeof( $quantity_field ) == 0 && ! $field->disableQuantity ? rgget( $id . '.3', $lead_value ) : $quantity;
							if ( empty( $product_quantity ) ) {
								continue;
							}

							if ( ! rgget( $id, $products ) ) {
								$products[ $id ] = array();
							}

							$products[ $id ]['name']     = $use_admin_label && ! rgempty( 'adminLabel', $field ) ? $field->adminLabel : $lead_value[ $id . '.1' ];
							$products[ $id ]['price']    = rgar( $lead_value, $id . '.2' );
							$products[ $id ]['quantity'] = $product_quantity;
						} elseif ( ! empty( $lead_value ) ) {

							if ( empty( $quantity ) ) {
								continue;
							}

							if ( ! rgar( $products, $id ) ) {
								$products[ $id ] = array();
							}

							if ( $field->inputType == 'price' ) {
								$name  = $field->label;
								$price = $lead_value;
							} else {
								list( $name, $price ) = explode( '|', $lead_value );
							}

							$products[ $id ]['name'] = ! $use_choice_text ? $name : RGFormsModel::get_choice_text( $field, $name );
							$include_field_label     = apply_filters( 'gform_product_info_name_include_field_label', false );
							if ( $field->inputType == ( 'radio' || 'select' ) && $include_field_label ) {
								$products[ $id ]['name'] = $field->label . " ({$products[$id]['name']})";
							}

							$products[ $id ]['price']    = $price;
							$products[ $id ]['quantity'] = $quantity;
							$products[ $id ]['options']  = array();
						}

						if ( isset( $products[ $id ] ) ) {
							$options = self::get_product_fields_by_type( $form, array( 'option' ), $id );
							foreach ( $options as $option ) {
								$option_value = RGFormsModel::get_lead_field_value( $lead, $option );
								$option_label = empty( $option['adminLabel'] ) ? $option['label'] : $option['adminLabel'];
								if ( is_array( $option_value ) ) {
									foreach ( $option_value as $value ) {
										$option_info = self::get_option_info( $value, $option, $use_choice_text );
										if ( ! empty( $option_info ) ) {
											$products[ $id ]['options'][] = array( 'field_label'  => rgar( $option, 'label' ),
											                                       'option_name'  => rgar( $option_info, 'name' ),
											                                       'option_label' => $option_label . ': ' . rgar( $option_info, 'name' ),
											                                       'price'        => rgar( $option_info, 'price' )
											);
										}
									}
								} elseif ( ! empty( $option_value ) ) {
									$option_info                  = self::get_option_info( $option_value, $option, $use_choice_text );
									$products[ $id ]['options'][] = array( 'field_label'  => rgar( $option, 'label' ),
									                                       'option_name'  => rgar( $option_info, 'name' ),
									                                       'option_label' => $option_label . ': ' . rgar( $option_info, 'name' ),
									                                       'price'        => rgar( $option_info, 'price' )
									);
								}
							}
						}
						break;
				}
			}

			$shipping_field    = GFAPI::get_fields_by_type( $form, array( 'shipping' ) );
			$shipping_price    = $shipping_name = '';
			$shipping_field_id = '';
			if ( ! empty( $shipping_field ) && ! RGFormsModel::is_field_hidden( $form, $shipping_field[0], array(), $lead ) ) {
				$shipping_price    = RGFormsModel::get_lead_field_value( $lead, $shipping_field[0] );
				$shipping_name     = $shipping_field[0]['label'];
				$shipping_field_id = $shipping_field[0]['id'];
				if ( $shipping_field[0]['inputType'] != 'singleshipping' && ! empty( $shipping_price ) ) {
					list( $shipping_method, $shipping_price ) = explode( '|', $shipping_price );
					$shipping_name = $shipping_field[0]['label'] . " ($shipping_method)";
				}
			}
			$shipping_price = self::to_number( $shipping_price );

			$product_info = array( 'products' => $products, 'shipping' => array( 'id' => $shipping_field_id, 'name' => $shipping_name, 'price' => $shipping_price ) );

			$product_info = gf_apply_filters( 'gform_product_info', $form['id'], $product_info, $form, $lead );

			// save static copy of product info (only for 'real' entries)
			if ( ! rgempty( 'id', $lead ) && ! empty( $product_info['products'] ) ) {
				gform_update_meta( $lead['id'], "gform_product_info_{$use_choice_text}_{$use_admin_label}", $product_info );
			}
		}

		return $product_info;
	}

	public static function get_order_total( $form, $lead ) {

		$products = self::get_product_fields( $form, $lead, false );

		return self::get_total( $products );
	}

	public static function get_total( $products ) {

		$total = 0;
		foreach ( $products['products'] as $product ) {

			$price = self::to_number( $product['price'] );
			if ( is_array( rgar( $product, 'options' ) ) ) {
				foreach ( $product['options'] as $option ) {
					$price += self::to_number( $option['price'] );
				}
			}
			$subtotal = floatval( $product['quantity'] ) * $price;
			$total += $subtotal;

		}

		$total += floatval( $products['shipping']['price'] );

		return $total;
	}

	public static function get_option_info( $value, $option, $use_choice_text ) {
		if ( empty( $value ) ) {
			return array();
		}

		list( $name, $price ) = explode( '|', $value );
		if ( $use_choice_text ) {
			$name = RGFormsModel::get_choice_text( $option, $name );
		}

		return array( 'name' => $name, 'price' => $price );
	}

	public static function gform_do_shortcode( $content ) {

		$is_ajax = false;
		$forms   = GFFormDisplay::get_embedded_forms( $content, $is_ajax );

		foreach ( $forms as $form ) {
			if ( headers_sent() ) {
				GFFormDisplay::print_form_scripts( $form, $is_ajax );
			} else {
				GFFormDisplay::enqueue_form_scripts( $form, $is_ajax );
			}
		}

		return do_shortcode( $content );
	}

	public static function spam_enabled( $form_id ) {
		$spam_enabled = self::akismet_enabled( $form_id ) || has_filter( 'gform_entry_is_spam' ) || has_filter( "gform_entry_is_spam_{$form_id}" );

		return $spam_enabled;
	}

	public static function has_akismet() {
		$akismet_exists = function_exists( 'akismet_http_post' ) || function_exists( 'Akismet::http_post' );

		return $akismet_exists;
	}

	public static function akismet_enabled( $form_id ) {

		if ( ! self::has_akismet() ) {
			return false;
		}

		// if no option is set, leave akismet enabled; otherwise, use option value true/false
		$enabled_by_setting = get_option( 'rg_gforms_enable_akismet' ) === false ? true : get_option( 'rg_gforms_enable_akismet' ) == true;
		$enabled_by_filter  = gf_apply_filters( 'gform_akismet_enabled', $form_id, $enabled_by_setting );

		return $enabled_by_filter;

	}

	public static function is_akismet_spam( $form, $lead ) {

		global $akismet_api_host, $akismet_api_port;

		$fields = self::get_akismet_fields( $form, $lead );

		//Submitting info to Akismet
		if ( defined( 'AKISMET_VERSION' ) && AKISMET_VERSION < 3.0 ) {
			//Akismet versions before 3.0
			$response = akismet_http_post( $fields, $akismet_api_host, '/1.1/comment-check', $akismet_api_port );
		} else {
			$response = Akismet::http_post( $fields, 'comment-check' );
		}
		$is_spam = trim( rgar( $response, 1 ) ) == 'true';

		return $is_spam;
	}

	public static function mark_akismet_spam( $form, $lead, $is_spam ) {

		global $akismet_api_host, $akismet_api_port;

		$fields = self::get_akismet_fields( $form, $lead );
		$as     = $is_spam ? 'spam' : 'ham';

		//Submitting info to Akismet
		if ( defined( 'AKISMET_VERSION' ) && AKISMET_VERSION < 3.0 ) {
			//Akismet versions before 3.0
			akismet_http_post( $fields, $akismet_api_host, '/1.1/submit-' . $as, $akismet_api_port );
		} else {
			Akismet::http_post( $fields, 'submit-' . $as );
		}
	}

	private static function get_akismet_fields( $form, $lead ) {

		$is_form_editor = GFCommon::is_form_editor();
		$is_entry_detail = GFCommon::is_entry_detail();
		$is_admin = $is_form_editor || $is_entry_detail;

		//Gathering Akismet information
		$akismet_info                         = array();
		$akismet_info['comment_type']         = 'gravity_form';
		$akismet_info['comment_author']       = self::get_akismet_field( 'name', $form, $lead );
		$akismet_info['comment_author_email'] = self::get_akismet_field( 'email', $form, $lead );
		$akismet_info['comment_author_url']   = self::get_akismet_field( 'website', $form, $lead );
		$akismet_info['comment_content']      = self::get_akismet_field( 'textarea', $form, $lead );
		$akismet_info['contact_form_subject'] = $form['title'];
		$akismet_info['comment_author_IP']    = $lead['ip'];
		$akismet_info['permalink']            = $lead['source_url'];
		$akismet_info['user_ip']              = preg_replace( '/[^0-9., ]/', '', $lead['ip'] );
		$akismet_info['user_agent']           = $lead['user_agent'];
		$akismet_info['referrer']             = $is_admin ? '' : $_SERVER['HTTP_REFERER'];
		$akismet_info['blog']                 = get_option( 'home' );

		$akismet_info = gf_apply_filters( 'gform_akismet_fields', $form['id'], $akismet_info, $form, $lead );

		return http_build_query( $akismet_info );
	}

	private static function get_akismet_field( $field_type, $form, $lead ) {
		$fields = GFAPI::get_fields_by_type( $form, array( $field_type ) );
		if ( empty( $fields ) ) {
			return '';
		}

		$value = RGFormsModel::get_lead_field_value( $lead, $fields[0] );
		switch ( $field_type ) {
			case 'name' :
				$value = GFCommon::get_lead_field_display( $fields[0], $value );
				break;
		}

		return $value;
	}

	public static function get_other_choice_value() {
		$value = apply_filters( 'gform_other_choice_value', esc_html__( 'Other' , 'gravityforms' ) );

		return $value;
	}

	public static function get_browser_class() {
		global $is_lynx, $is_gecko, $is_IE, $is_opera, $is_NS4, $is_safari, $is_chrome, $is_iphone, $post;

		$classes = array();

		//adding browser related class
		if ( $is_lynx ) {
			$classes[] = 'gf_browser_lynx';
		} else if ( $is_gecko ) {
			$classes[] = 'gf_browser_gecko';
		} else if ( $is_opera ) {
			$classes[] = 'gf_browser_opera';
		} else if ( $is_NS4 ) {
			$classes[] = 'gf_browser_ns4';
		} else if ( $is_safari ) {
			$classes[] = 'gf_browser_safari';
		} else if ( $is_chrome ) {
			$classes[] = 'gf_browser_chrome';
		} else if ( $is_IE ) {
			$classes[] = 'gf_browser_ie';
		} else {
			$classes[] = 'gf_browser_unknown';
		}


		//adding IE version
		if ( $is_IE ) {
			if ( strpos( $_SERVER['HTTP_USER_AGENT'], 'MSIE 6' ) !== false ) {
				$classes[] = 'gf_browser_ie6';
			} else if ( strpos( $_SERVER['HTTP_USER_AGENT'], 'MSIE 7' ) !== false ) {
				$classes[] = 'gf_browser_ie7';
			}
			if ( strpos( $_SERVER['HTTP_USER_AGENT'], 'MSIE 8' ) !== false ) {
				$classes[] = 'gf_browser_ie8';
			}
			if ( strpos( $_SERVER['HTTP_USER_AGENT'], 'MSIE 9' ) !== false ) {
				$classes[] = 'gf_browser_ie9';
			}
		}

		if ( $is_iphone ) {
			$classes[] = 'gf_browser_iphone';
		}

		return implode( ' ', $classes );
	}

	public static function create_post( $form, &$lead ) {
		$disable_post = gf_apply_filters( 'gform_disable_post_creation', $form['id'], false, $form, $lead );
		$post_id      = 0;
		if ( ! $disable_post ) {
			//creates post if the form has any post fields
			$post_id = RGFormsModel::create_post( $form, $lead );
		}

		return $post_id;
	}

	public static function evaluate_conditional_logic( $logic, $form, $lead ) {

		if ( ! $logic || ! is_array( rgar( $logic, 'rules' ) ) ) {
			return true;
		}

		$entry_meta_keys = array_keys( GFFormsModel::get_entry_meta( $form['id'] ) );
		$match_count     = 0;
		if ( is_array( $logic['rules'] ) ) {
			foreach ( $logic['rules'] as $rule ) {

				if ( in_array( $rule['fieldId'], $entry_meta_keys ) ) {
					$is_value_match = GFFormsModel::is_value_match( rgar( $lead, $rule['fieldId'] ), $rule['value'], $rule['operator'], $rule, $form );
				} else {
					$source_field   = GFFormsModel::get_field( $form, $rule['fieldId'] );
					$field_value    = empty( $lead ) ? GFFormsModel::get_field_value( $source_field, array() ) : GFFormsModel::get_lead_field_value( $lead, $source_field );
					$is_value_match = GFFormsModel::is_value_match( $field_value, $rule['value'], $rule['operator'], $source_field, $rule, $form );
				}

				if ( $is_value_match ) {
					$match_count ++;
				}
			}
		}

		$do_action = ( $logic['logicType'] == 'all' && $match_count == sizeof( $logic['rules'] ) ) || ( $logic['logicType'] == 'any' && $match_count > 0 );

		return $do_action;
	}

	public static function get_card_types() {
		$cards = array(

			array(
				'name'     => 'American Express',
				'slug'     => 'amex',
				'lengths'  => '15',
				'prefixes' => '34,37',
				'checksum' => true,
			),
			array(
				'name'     => 'Discover',
				'slug'     => 'discover',
				'lengths'  => '16',
				'prefixes' => '6011,622,64,65',
				'checksum' => true,
			),
			array(
				'name'     => 'MasterCard',
				'slug'     => 'mastercard',
				'lengths'  => '16',
				'prefixes' => '51,52,53,54,55',
				'checksum' => true,
			),
			array(
				'name'     => 'Visa',
				'slug'     => 'visa',
				'lengths'  => '13,16',
				'prefixes' => '4,417500,4917,4913,4508,4844',
				'checksum' => true,
			),
			array(
				'name'     => 'JCB',
				'slug'     => 'jcb',
				'lengths'  => '16',
				'prefixes' => '35',
				'checksum' => true,
			),
			array(
				'name'     => 'Maestro',
				'slug'     => 'maestro',
				'lengths'  => '12,13,14,15,16,18,19',
				'prefixes' => '5018,5020,5038,6304,6759,6761',
				'checksum' => true,
			),
		);

		$cards = apply_filters( 'gform_creditcard_types', $cards );

		return $cards;
	}

	public static function get_card_type( $number ) {

		//removing spaces from number
		$number = str_replace( ' ', '', $number );

		if ( empty( $number ) ) {
			return false;
		}

		$cards = self::get_card_types();

		$matched_card = false;
		foreach ( $cards as $card ) {
			if ( self::matches_card_type( $number, $card ) ) {
				$matched_card = $card;
				break;
			}
		}

		if ( $matched_card && $matched_card['checksum'] && ! self::is_valid_card_checksum( $number ) ) {
			$matched_card = false;
		}

		return $matched_card ? $matched_card : false;

	}

	private static function matches_card_type( $number, $card ) {

		//checking prefix
		$prefixes       = explode( ',', $card['prefixes'] );
		$matches_prefix = false;
		foreach ( $prefixes as $prefix ) {
			if ( preg_match( "|^{$prefix}|", $number ) ) {
				$matches_prefix = true;
				break;
			}
		}

		//checking length
		$lengths        = explode( ',', $card['lengths'] );
		$matches_length = false;
		foreach ( $lengths as $length ) {
			if ( strlen( $number ) == absint( $length ) ) {
				$matches_length = true;
				break;
			}
		}

		return $matches_prefix && $matches_length;

	}

	private static function is_valid_card_checksum( $number ) {
		$checksum   = 0;
		$num        = 0;
		$multiplier = 1;

		// Process each character starting at the right
		for ( $i = strlen( $number ) - 1; $i >= 0; $i -- ) {

			//Multiply current digit by multiplier (1 or 2)
			$num = $number{$i} * $multiplier;

			// If the result is in greater than 9, add 1 to the checksum total
			if ( $num >= 10 ) {
				$checksum ++;
				$num -= 10;
			}

			//Update checksum
			$checksum += $num;

			//Update multiplier
			$multiplier = $multiplier == 1 ? 2 : 1;
		}

		return $checksum % 10 == 0;

	}

	public static function is_wp_version( $min_version ) {
		return ! version_compare( get_bloginfo( 'version' ), "{$min_version}.dev1", '<' );
	}

	public static function add_categories_as_choices( $field, $value ) {

		$choices         = $inputs = array();
		$is_post         = isset( $_POST['gform_submit'] );
		$has_placeholder = $field->categoryInitialItemEnabled && RGFormsModel::get_input_type( $field ) == 'select';

		if ( $has_placeholder ) {
			$choices[] = array( 'text' => $field->categoryInitialItem, 'value' => '', 'isSelected' => true );
		}

		$display_all = $field->displayAllCategories;

		$args = array( 'hide_empty' => false, 'orderby' => 'name' );

		if ( ! $display_all ) {
			foreach ( $field->choices as $field_choice_to_include ) {
				$args['include'][] = $field_choice_to_include['value'];
			}
		}

		$args  = gf_apply_filters( 'gform_post_category_args', $field->id, $args, $field );
		$terms = get_terms( 'category', $args );

		$terms_copy = unserialize( serialize( $terms ) ); // deep copy the terms to avoid repeating GFCategoryWalker on previously cached terms.
		$walker     = new GFCategoryWalker();
		$categories = $walker->walk( $terms_copy, 0, array( 0 ) ); // 3rd parameter prevents notices triggered by $walker::display_element() function which checks $args[0]

		foreach ( $categories as $category ) {
			if ( $display_all ) {
				$selected  = $value == $category->term_id ||
					(
						empty( $value ) &&
						get_option( 'default_category' ) == $category->term_id &&
						RGFormsModel::get_input_type( $field ) == 'select' && // only preselect default category on select fields
						! $is_post &&
						! $has_placeholder
					);
				$choices[] = array( 'text' => $category->name, 'value' => $category->term_id, 'isSelected' => $selected );
			} else {
				foreach ( $field->choices as $field_choice ) {
					if ( $field_choice['value'] == $category->term_id ) {
						$choices[] = array( 'text' => $category->name, 'value' => $category->term_id );
						break;
					}
				}
			}
		}

		if ( empty( $choices ) ) {
			$choices[] = array( 'text' => 'You must select at least one category.', 'value' => '' );
		}

		$choice_number = 1;
		foreach ( $choices as $choice ) {

			if ( $choice_number % 10 == 0 ) {
				//hack to skip numbers ending in 0. so that 5.1 doesn't conflict with 5.10
				$choice_number ++;
			}

			$input_id = $field->id . '.' . $choice_number;
			$inputs[] = array( 'id' => $input_id, 'label' => $choice['text'], 'name' => '' );
			$choice_number ++;
		}

		$field->choices = $choices;

		$is_form_editor = GFCommon::is_form_editor();
		$is_entry_detail = GFCommon::is_entry_detail();
		$is_admin = $is_form_editor || $is_entry_detail;

		$form_id = $is_admin ? rgget( 'id' ) : $field->formId;

		/**
		 * Allows you to filter (modify) the post cateogry choices when using post fields
		 *
		 * @param array $field The Cateogry choices field
		 * @param int $form_id The CUrrent form ID
		 */
		$field->choices = gf_apply_filters( 'gform_post_category_choices', array(
			$form_id,
			$field->id
		), $field->choices, $field, $form_id );

		if ( RGFormsModel::get_input_type( $field ) == 'checkbox' ) {
			$field->inputs = $inputs;
		}

		return $field;
	}

	public static function prepare_post_category_value( $value, $field, $mode = 'entry_detail' ) {

		if ( ! is_array( $value ) ) {
			$value = explode( ',', $value );
		}

		$cat_names = array();
		$cat_ids   = array();
		foreach ( $value as $cat_string ) {
			$ary      = explode( ':', $cat_string );
			$cat_name = count( $ary ) > 0 ? $ary[0] : '';
			$cat_id   = count( $ary ) > 1 ? $ary[1] : $ary[0];

			if ( ! empty( $cat_name ) ) {
				$cat_names[] = $cat_name;
			}

			if ( ! empty( $cat_id ) ) {
				$cat_ids[] = $cat_id;
			}
		}

		sort( $cat_names );

		switch ( $mode ) {
			case 'entry_list':
				$value = self::implode_non_blank( ', ', $cat_names );
				break;
			case 'entry_detail':
				$value = RGFormsModel::get_input_type( $field ) == 'checkbox' ? $cat_names : self::implode_non_blank( ', ', $cat_names );
				break;
			case 'conditional_logic':
				$value = array_values( $cat_ids );
				break;
		}

		return $value;
	}

	public static function calculate( $field, $form, $lead ) {

		$formula = (string) apply_filters( 'gform_calculation_formula', $field->calculationFormula, $field, $form, $lead );

		// replace multiple spaces and new lines with single space
		// @props: http://stackoverflow.com/questions/3760816/remove-new-lines-from-string
		$formula = trim( preg_replace( '/\s+/', ' ', $formula ) );

		preg_match_all( '/{[^{]*?:(\d+(\.\d+)?)(:(.*?))?}/mi', $formula, $matches, PREG_SET_ORDER );

		if ( is_array( $matches ) ) {
			foreach ( $matches as $match ) {

				list( $text, $input_id ) = $match;
				$value   = self::get_calculation_value( $input_id, $form, $lead );
				$value   = apply_filters( 'gform_merge_tag_value_pre_calculation', $value, $input_id, rgar( $match, 4 ), $field, $form, $lead );
				$formula = str_replace( $text, $value, $formula );

			}
		}

		$result = preg_match( '/^[0-9 -\/*\(\)]+$/', $formula ) ? eval( "return {$formula};" ) : false;
        $result = apply_filters( 'gform_calculation_result', $result, $formula, $field, $form, $lead );

		return $result;
	}

	public static function round_number( $number, $rounding ) {
		if ( is_numeric( $rounding ) && $rounding >= 0 ) {
			$number = round( $number, $rounding );
		}

		return $number;
	}

	public static function get_calculation_value( $field_id, $form, $lead ) {

		$filters = array( 'price', 'value', '' );
		$value   = false;

		foreach ( $filters as $filter ) {
			if ( is_numeric( $value ) ) {
				//value found, exit loop
				break;
			}
			$value = GFCommon::to_number( GFCommon::replace_variables( "{:{$field_id}:$filter}", $form, $lead ) );
		}

		if ( ! $value || ! is_numeric( $value ) ) {
			GFCommon::log_debug( "GFCommon::get_calculation_value(): No value or non-numeric value available for field #{$field_id}. Returning zero instead." );
			$value = 0;
		}

		return $value;
	}

	public static function conditional_shortcode( $attributes, $content = null ) {

		extract(
			shortcode_atts(
				array(
					'merge_tag' => '',
					'condition' => '',
					'value'     => '',
				), $attributes
			)
		);

		return RGFormsModel::matches_operation( $merge_tag, $value, $condition ) ? do_shortcode( $content ) : '';

	}

	public static function is_valid_for_calcuation( $field ) {

		$supported_input_types   = array( 'text', 'select', 'number', 'checkbox', 'radio', 'hidden', 'singleproduct', 'price', 'hiddenproduct', 'calculation', 'singleshipping' );
		$unsupported_field_types = array( 'category' );
		$input_type              = RGFormsModel::get_input_type( $field );

		return in_array( $input_type, $supported_input_types ) && ! in_array( $input_type, $unsupported_field_types );
	}

	public static function log_error( $message ) {
		if ( class_exists( 'GFLogging' ) ) {
			GFLogging::include_logger();
			GFLogging::log_message( 'gravityforms', $message, KLogger::ERROR );
		}
	}

	public static function log_debug( $message ) {
		if ( class_exists( 'GFLogging' ) ) {
			GFLogging::include_logger();
			GFLogging::log_message( 'gravityforms', $message, KLogger::DEBUG );
		}
	}

	public static function echo_if( $condition, $text ) {
		_deprecated_function( 'GFCommon::echo_if() is deprecated', '1.9.9', 'Use checked() or selected() instead.' );

		switch ( $text ) {
			case 'checked':
				$text = 'checked="checked"';
				break;
			case 'selected':
				$text = 'selected="selected"';
		}

		echo $condition ? $text : '';
	}

	public static function gf_global( $echo = true ) {

		require_once( GFCommon::get_base_path() . '/currency.php' );

		$gf_global                       = array();
		$gf_global['gf_currency_config'] = RGCurrency::get_currency( GFCommon::get_currency() );
		$gf_global['base_url']           = GFCommon::get_base_url();
		$gf_global['number_formats']     = array();
		$gf_global['spinnerUrl']         = GFCommon::get_base_url() . '/images/spinner.gif';

		$gf_global_json = 'var gf_global = ' . json_encode( $gf_global ) . ';';

		if ( ! $echo ) {
			return $gf_global_json;
		}

		echo $gf_global_json;
	}

	public static function gf_vars( $echo = true ) {
		if ( ! class_exists( 'RGCurrency' ) ) {
			require_once( 'currency.php' );
		}

		$gf_vars                            = array();
		$gf_vars['active']                  = esc_attr__( 'Active' , 'gravityforms' );
		$gf_vars['inactive']                = esc_attr__( 'Inactive' , 'gravityforms' );
		$gf_vars['save']                    = esc_html__( 'Save' , 'gravityforms' );
		$gf_vars['update']                  = esc_html__( 'Update' , 'gravityforms' );
		$gf_vars['previousLabel']           = esc_html__( 'Previous' , 'gravityforms' );
		$gf_vars['selectFormat']            = esc_html__( 'Select a format' , 'gravityforms' );
		$gf_vars['editToViewAll']           = esc_html__( '5 of %d items shown. Edit field to view all' , 'gravityforms' );
		$gf_vars['enterValue']              = esc_html__( 'Enter a value' , 'gravityforms' );
		$gf_vars['formTitle']               = esc_html__( 'Untitled Form' , 'gravityforms' );
		$gf_vars['formDescription']         = esc_html__( 'We would love to hear from you! Please fill out this form and we will get in touch with you shortly.' , 'gravityforms' );
		$gf_vars['formConfirmationMessage'] = esc_html__( 'Thanks for contacting us! We will get in touch with you shortly.' , 'gravityforms' );
		$gf_vars['buttonText']              = esc_html__( 'Submit' , 'gravityforms' );
		$gf_vars['loading']                 = esc_html__( 'Loading...' , 'gravityforms' );
		$gf_vars['thisFieldIf']             = esc_html__( 'this field if', 'gravityforms' );
		$gf_vars['thisPage']                = esc_html__( 'this page' , 'gravityforms' );
		$gf_vars['thisFormButton']          = esc_html__( 'this form button if', 'gravityforms' );
		$gf_vars['show']                    = esc_html__( 'Show', 'gravityforms' );
		$gf_vars['hide']                    = esc_html__( 'Hide', 'gravityforms' );
		$gf_vars['all']                     = esc_html( _x( 'All', 'Conditional Logic', 'gravityforms' ) );
		$gf_vars['any']                     = esc_html( _x( 'Any', 'Conditional Logic', 'gravityforms' ) );
		$gf_vars['ofTheFollowingMatch']     = esc_html__( 'of the following match:', 'gravityforms' );
		$gf_vars['is']                      = esc_html__( 'is', 'gravityforms' );
		$gf_vars['isNot']                   = esc_html__( 'is not', 'gravityforms' );
		$gf_vars['greaterThan']             = esc_html__( 'greater than', 'gravityforms' );
		$gf_vars['lessThan']                = esc_html__( 'less than', 'gravityforms' );
		$gf_vars['contains']                = esc_html__( 'contains', 'gravityforms' );
		$gf_vars['startsWith']              = esc_html__( 'starts with', 'gravityforms' );
		$gf_vars['endsWith']                = esc_html__( 'ends with', 'gravityforms' );

		$gf_vars['thisConfirmation']                 = esc_html__( 'Use this confirmation if', 'gravityforms' );
		$gf_vars['thisNotification']                 = esc_html__( 'Send this notification if', 'gravityforms' );
		$gf_vars['confirmationSave']                 = esc_html__( 'Save', 'gravityforms' );
		$gf_vars['confirmationSaving']               = esc_html__( 'Saving...', 'gravityforms' );
		$gf_vars['confirmationAreYouSure']           = __( 'Are you sure you wish to cancel these changes?', 'gravityforms' );
		$gf_vars['confirmationIssueSaving']          = __( 'There was an issue saving this confirmation.', 'gravityforms' );
		$gf_vars['confirmationConfirmDelete']        = __( 'Are you sure you wish to delete this confirmation?', 'gravityforms' );
		$gf_vars['confirmationIssueDeleting']        = __( 'There was an issue deleting this confirmation.', 'gravityforms' );
		$gf_vars['confirmationConfirmDiscard']       = __( 'There are unsaved changes to the current confirmation. Would you like to discard these changes?', 'gravityforms' );
		$gf_vars['confirmationDefaultName']          = __( 'Untitled Confirmation', 'gravityforms' );
		$gf_vars['confirmationDefaultMessage']       = __( 'Thanks for contacting us! We will get in touch with you shortly.', 'gravityforms' );
		$gf_vars['confirmationInvalidPageSelection'] = __( 'Please select a page.', 'gravityforms' );
		$gf_vars['confirmationInvalidRedirect']      = __( 'Please enter a URL.', 'gravityforms' );
		$gf_vars['confirmationInvalidName']          = __( 'Please enter a confirmation name.', 'gravityforms' );

		$gf_vars['conditionalLogicDependency']           = __( "This form contains conditional logic dependent upon this field. Are you sure you want to delete this field? 'OK' to delete, 'Cancel' to abort.", 'gravityforms' );
		$gf_vars['conditionalLogicDependencyChoice']     = __( "This form contains conditional logic dependent upon this choice. Are you sure you want to delete this choice? 'OK' to delete, 'Cancel' to abort.", 'gravityforms' );
		$gf_vars['conditionalLogicDependencyChoiceEdit'] = __( "This form contains conditional logic dependent upon this choice. Are you sure you want to modify this choice? 'OK' to delete, 'Cancel' to abort.", 'gravityforms' );

		$gf_vars['mergeTagsTooltip'] = '<h6>' . esc_html__( 'Merge Tags', 'gravityforms' ) . '</h6>' . esc_html__( 'Merge tags allow you to dynamically populate submitted field values in your form content wherever this merge tag icon is present.', 'gravityforms' );

		$gf_vars['baseUrl']              = GFCommon::get_base_url();
		$gf_vars['gf_currency_config']   = RGCurrency::get_currency( GFCommon::get_currency() );
		$gf_vars['otherChoiceValue']     = GFCommon::get_other_choice_value();
		$gf_vars['isFormTrash']          = false;
		$gf_vars['currentlyAddingField'] = false;

		$gf_vars['addFieldFilter']    = esc_html__( 'Add a condition' , 'gravityforms' );
		$gf_vars['removeFieldFilter'] = esc_html__( 'Remove a condition' , 'gravityforms' );
		$gf_vars['filterAndAny']      = esc_html__( 'Include results if {0} match:' , 'gravityforms' );

		$gf_vars['customChoices']     = esc_html__( 'Custom Choices', 'gravityforms' );
		$gf_vars['predefinedChoices'] = esc_html__( 'Predefined Choices', 'gravityforms' );


		if ( is_admin() && rgget( 'id' ) ) {
			$form                 = RGFormsModel::get_form_meta( rgget( 'id' ) );
			$gf_vars['mergeTags'] = GFCommon::get_merge_tags( $form['fields'], '', false );
		}

		$gf_vars_json = 'var gf_vars = ' . json_encode( $gf_vars ) . ';';

		if ( ! $echo ) {
			return $gf_vars_json;
		} else {
			echo $gf_vars_json;
		}
	}

	public static function is_bp_active() {
		return defined( 'BP_VERSION' ) ? true : false;
	}

	public static function add_message( $message, $is_error = false ) {
		if ( $is_error ) {
			self::$errors[] = $message;
		} else {
			self::$messages[] = $message;
		}
	}

	public static function add_error_message( $message ) {
		self::add_message( $message, true );
	}

	public static function display_admin_message( $errors = false, $messages = false ) {

		if ( ! $errors ) {
			$errors = self::$errors;
		}

		if ( ! $messages ) {
			$messages = self::$messages;
		}

		$errors   = apply_filters( 'gform_admin_error_messages', $errors );
		$messages = apply_filters( 'gform_admin_messages', $messages );

		if ( ! empty( $errors ) ) {
			?>
			<div class="error below-h2">
				<?php if ( count( $errors ) > 1 ) { ?>
					<ul style="margin: 0.5em 0 0; padding: 2px;">
						<li><?php echo implode( '</li><li>', $errors ); ?></li>
					</ul>
				<?php } else { ?>
					<p><?php echo $errors[0]; ?></p>
				<?php } ?>
			</div>
		<?php
		} else if ( ! empty( $messages ) ) {
			?>
			<div id="message" class="updated below-h2">
				<?php if ( count( $messages ) > 1 ) { ?>
					<ul style="margin: 0.5em 0 0; padding: 2px;">
						<li><?php echo implode( '</li><li>', $messages ); ?></li>
					</ul>
				<?php } else { ?>
					<p><strong><?php echo $messages[0]; ?></strong></p>
				<?php } ?>
			</div>
		<?php
		}

	}

	private static function requires_gf_vars() {
		$dependent_scripts = array( 'gform_form_admin', 'gform_gravityforms', 'gform_form_editor', 'gform_field_filter' );
		foreach ( $dependent_scripts as $script ) {
			if ( wp_script_is( $script ) ) {
				return true;
			}
		}

		return false;
	}

	public static function maybe_output_gf_vars() {
		if ( self::requires_gf_vars() ) {
			echo '<script type="text/javascript">' . self::gf_vars( false ) . '</script>';
		}
	}

	public static function maybe_add_leading_zero( $value ) {
		$first_char = GFCommon::safe_substr( $value, 0, 1, 'utf-8' );
		if ( in_array( $first_char, array( '.', ',' ) ) ) {
			$value = '0' . $value;
		}

		return $value;
	}

	// used by the gfFieldFilterUI() jQuery plugin
	public static function get_field_filter_settings( $form ) {

		$all_fields = $form['fields'];

		// set up filters
		$fields        = $all_fields;
		$exclude_types = array( 'rank', 'page', 'html' );

		$operators_by_input_type = array(
			'default'     => array( 'is', 'isnot', '>', '<', ),
			'name'        => array( 'is', 'isnot', '>', '<', 'contains' ),
			'address'     => array( 'is', 'isnot', '>', '<', 'contains' ),
			'text'        => array( 'is', 'isnot', '>', '<', 'contains' ),
			'textarea'    => array( 'is', 'isnot', '>', '<', 'contains' ),
			'checkbox'    => array( 'is' ),
			'multiselect' => array( 'contains' ),
			'number'      => array( 'is', 'isnot', '>', '<' ),
			'select'      => array( 'is', 'isnot', '>', '<' ),
			'likert'      => array( 'is', 'isnot' ),
			'list'        => array( 'contains' )
		);

		for ( $i = 0; $i < count( $all_fields ); $i ++ ) {
			$input_type = GFFormsmodel::get_input_type( $all_fields[ $i ] );
			if ( in_array( $input_type, $exclude_types ) ) {
				unset( $fields[ $i ] );
			}
		}
		$fields = array_values( $fields );

		$field_filters = array(
			array(
				'key'       => '0',
				'text'      => esc_html__( 'Any form field' , 'gravityforms' ),
				'operators' => array( 'contains', 'is' ),
				'preventMultiple' => false,
			),
		);

		foreach ( $fields as $field ) {

			$input_type = GFFormsModel::get_input_type( $field );

			$operators = isset( $operators_by_input_type[ $input_type ] ) ? $operators_by_input_type[ $input_type ] : $operators_by_input_type['default'];

			if ( $field->type == 'product' && in_array( $input_type, array( 'radio', 'select' ) ) ) {
				$operators = array( 'is' );
			} elseif ( ! isset( $field->choices ) && ! in_array( 'contains', $operators ) ) {
				$operators[] = 'contains';
			}

			$field_filter = array();
			$key          = $field->id;
			if ( $input_type == 'likert' && $field->gsurveyLikertEnableMultipleRows ) {
				// multi-row likert fields
				$field_filter['key']   = $key;
				$field_filter['group'] = true;
				$field_filter['text']  = GFFormsModel::get_label( $field );
				$sub_filters           = array();
				$rows                  = $field->gsurveyLikertRows;
				foreach ( $rows as $row ) {
					$sub_filter                    = array();
					$sub_filter['key']             = $key . '|' . rgar( $row, 'value' );
					$sub_filter['text']            = rgar( $row, 'text' );
					$sub_filter['type']            = 'field';
					$sub_filter['preventMultiple'] = false;
					$sub_filter['operators']       = $operators;
					$sub_filter['values']          = $field->choices;
					$sub_filters[]                 = $sub_filter;
				}
				$field_filter['filters'] = $sub_filters;
			} elseif ( ( $input_type == 'name' && $field->nameFormat !== '' && $field->nameFormat !== 'simple') || $input_type == 'address' ) {
				// standard two input name field
				$field_filter['key']   = $key;
				$field_filter['group'] = true;
				$field_filter['text']  = GFFormsModel::get_label( $field );
				$sub_filters           = array();
				$inputs                = $field->inputs;
				foreach ( $inputs as $input ) {
					$sub_filter                    = array();
					$sub_filter['key']             = rgar( $input, 'id' );
					$sub_filter['text']            = rgar( $input, 'label' );
					$sub_filter['preventMultiple'] = false;
					$sub_filter['operators']       = $operators;
					$sub_filters[]                 = $sub_filter;
				}
				$field_filter['filters'] = $sub_filters;
			} elseif( $input_type == 'date') {
				$field_filter['key']             = $key;
				$field_filter['preventMultiple'] = false;
				$field_filter['text']            = GFFormsModel::get_label( $field );

				$field_filter['operators'] = $operators;

				$field_filter['placeholder'] = esc_html__('yyyy-mm-dd', 'gravityforms' );
				$field_filter['cssClass'] = 'datepicker ymd_dash';
			} else {
				$field_filter['key']             = $key;
				$field_filter['preventMultiple'] = false;
				$field_filter['text']            = GFFormsModel::get_label( $field );

				$field_filter['operators'] = $operators;

				if ( isset( $field->choices ) ) {
					$field_filter['values'] = $field->choices;
				}
			}
			$field_filters[] = $field_filter;

		}
		$form_id            = $form['id'];
		$entry_meta_filters = self::get_entry_meta_filter_settings( $form_id );
		$field_filters      = array_merge( $field_filters, $entry_meta_filters );
		$field_filters      = array_values( $field_filters ); // reset the numeric keys in case some filters have been unset
		$info_filters       = self::get_entry_info_filter_settings();
		$field_filters      = array_merge( $field_filters, $info_filters );
		$field_filters      = array_values( $field_filters );

		return $field_filters;
	}

	public static function get_entry_info_filter_settings() {
		$settings     = array();
		$info_columns = self::get_entry_info_filter_columns();
		foreach ( $info_columns as $key => $info_column ) {
			$info_column['key']             = $key;
			$info_column['preventMultiple'] = false;
			$settings[]                     = $info_column;
		}

		return $settings;
	}

	public static function get_entry_info_filter_columns( $get_users = true ) {
		$account_choices = array();
		if ( $get_users ) {
			$args            = apply_filters( 'gform_filters_get_users', array( 'number' => 200 ) );
			$accounts        = get_users( $args );
			$account_choices = array();
			foreach ( $accounts as $account ) {
				$account_choices[] = array( 'text' => $account->user_login, 'value' => $account->ID );
			}
		}

		return array(
			'entry_id'       => array(
				'text'      => esc_html__( 'Entry ID' , 'gravityforms' ),
				'operators' => array( 'is', 'isnot', '>', '<' )
			),
			'date_created'   => array(
				'text'        => esc_html__( 'Entry Date' , 'gravityforms' ),
				'operators'   => array( 'is', '>', '<' ),
				'placeholder' => __( 'yyyy-mm-dd' , 'gravityforms' ),
				'cssClass' => 'datepicker ymd_dash',
			),
			'is_starred'     => array(
				'text'      => esc_html__( 'Starred' , 'gravityforms' ),
				'operators' => array( 'is', 'isnot' ),
				'values'    => array(
					array(
						'text'  => 'Yes',
						'value' => '1',
					),
					array(
						'text'  => 'No',
						'value' => '0',
					),
				)
			),
			'ip'             => array(
				'text'      => esc_html__( 'IP Address' , 'gravityforms' ),
				'operators' => array( 'is', 'isnot', '>', '<', 'contains' )
			),
			'source_url'     => array(
				'text'      => esc_html__( 'Source URL' , 'gravityforms' ),
				'operators' => array( 'is', 'isnot', '>', '<', 'contains' )
			),
			'payment_status' => array(
				'text'      => esc_html__( 'Payment Status' , 'gravityforms' ),
				'operators' => array( 'is', 'isnot' ),
				'values'    => array(
					array(
						'text'  => 'Paid',
						'value' => 'Paid',
					),
					array(
						'text'  => 'Processing',
						'value' => 'Processing',
					),
					array(
						'text'  => 'Failed',
						'value' => 'Failed',
					),
					array(
						'text'  => 'Active',
						'value' => 'Active',
					),
					array(
						'text'  => 'Cancelled',
						'value' => 'Cancelled',
					),
				)
			),
			'payment_date'   => array(
				'text'      => esc_html__( 'Payment Date' , 'gravityforms' ),
				'operators' => array( 'is', 'isnot', '>', '<' ),
				'placeholder' => __( 'yyyy-mm-dd' , 'gravityforms' ),
				'cssClass' => 'datepicker ymd_dash',
			),
			'payment_amount' => array(
				'text'      => esc_html__( 'Payment Amount' , 'gravityforms' ),
				'operators' => array( 'is', 'isnot', '>', '<', 'contains' )
			),
			'transaction_id' => array(
				'text'      => esc_html__( 'Transaction ID' , 'gravityforms' ),
				'operators' => array( 'is', 'isnot', '>', '<', 'contains' )
			),
			'created_by'     => array(
				'text'      => esc_html__( 'User' , 'gravityforms' ),
				'operators' => array( 'is', 'isnot' ),
				'values'    => $account_choices,
			)
		);
	}

	public static function get_entry_meta_filter_settings( $form_id ) {
		$filters    = array();
		$entry_meta = GFFormsModel::get_entry_meta( $form_id );
		if ( empty( $entry_meta ) ) {
			return $filters;
		}

		foreach ( $entry_meta as $key => $meta ) {
			if ( isset( $meta['filter'] ) ) {
				$filter                    = array();
				$filter['key']             = $key;
				$filter['preventMultiple'] = isset( $meta['filter']['preventMultiple'] ) ? $meta['filter']['preventMultiple'] : false;
				$filter['text']            = rgar( $meta, 'label' );
				$filter['operators']       = isset( $meta['filter']['operators'] ) ? $meta['filter']['operators'] : array( 'is', 'isnot' );
				if ( isset( $meta['filter']['choices'] ) ) {
					$filter['values'] = $meta['filter']['choices'];
				}
				$filters[] = $filter;
			}
		}

		return $filters;
	}


	public static function get_field_filters_from_post( $form ) {
		$field_filters = array();
		$filter_fields = rgpost( 'f' );
		if ( is_array( $filter_fields ) ) {
			$filter_operators = rgpost( 'o' );
			$filter_values    = rgpost( 'v' );
			for ( $i = 0; $i < count( $filter_fields ); $i ++ ) {
				$field_filter = array();
				$key          = $filter_fields[ $i ];
				if ( 'entry_id' == $key ) {
					$key = 'id';
				}
				$operator       = $filter_operators[ $i ];
				$val            = $filter_values[ $i ];
				$strpos_row_key = strpos( $key, '|' );
				if ( $strpos_row_key !== false ) { //multi-row likert
					$key_array = explode( '|', $key );
					$key       = $key_array[0];
					$val       = $key_array[1] . ':' . $val;
				}
				$field_filter['key']      = $key;

				$field = GFFormsModel::get_field( $form, $key );
				if ( $field ) {
					$input_type = GFFormsModel::get_input_type( $field );
					if ( $field->type == 'product' && in_array( $input_type, array( 'radio', 'select' ) ) ) {
						$operator = 'contains';
					}
				}

				$field_filter['operator'] = $operator;
				$field_filter['value']    = $val;
				$field_filters[]          = $field_filter;
			}
		}
		$field_filters['mode'] = rgpost( 'mode' );

		return $field_filters;
	}

	public static function has_multifile_fileupload_field( $form ) {
		$fileupload_fields = GFAPI::get_fields_by_type( $form, array( 'fileupload', 'post_custom_field' ) );
		if ( is_array( $fileupload_fields ) ) {
			foreach ( $fileupload_fields as $field ) {
				if ( $field->multipleFiles ) {
					return true;
				}
			}
		}

		return false;
	}

	public static function localize_gform_gravityforms_multifile() {
		wp_localize_script(
			'gform_gravityforms', 'gform_gravityforms', array(
				'strings' => array(
					'invalid_file_extension' => esc_html__( 'This type of file is not allowed. Must be one of the following: ' , 'gravityforms' ),
					'delete_file'            => esc_html__( 'Delete this file' , 'gravityforms' ),
					'in_progress'            => esc_html__( 'in progress' , 'gravityforms' ),
					'file_exceeds_limit'     => esc_html__( 'File exceeds size limit' , 'gravityforms' ),
					'illegal_extension'      => esc_html__( 'This type of file is not allowed.' , 'gravityforms' ),
					'max_reached'            => esc_html__( 'Maximum number of files reached' , 'gravityforms' ),
					'unknown_error'          => esc_html__( 'There was a problem while saving the file on the server' , 'gravityforms' ),
					'currently_uploading'    => esc_html__( 'Please wait for the uploading to complete' , 'gravityforms' ),
					'cancel'                 => esc_html__( 'Cancel' , 'gravityforms' ),
					'cancel_upload'          => esc_html__( 'Cancel this upload' , 'gravityforms' ),
					'cancelled'              => esc_html__( 'Cancelled' , 'gravityforms' )
				),
				'vars'    => array(
					'images_url' => GFCommon::get_base_url() . '/images'
				)
			)
		);
	}

	public static function send_resume_link( $message, $subject, $email, $embed_url, $resume_token ) {

		$from      = get_bloginfo( 'admin_email' );
		$from_name = get_bloginfo( 'name' );

		$message_format = 'html';

		$resume_url  = add_query_arg( array( 'gf_token' => $resume_token ), $embed_url );
		$resume_url = esc_url( $resume_url );
		$resume_link = "<a href='{$resume_url}'>{$resume_url}</a>";
		$message .= $resume_link;

		self::send_email( $from, $email, '', $from, $subject, $message, $from_name, $message_format );
	}

	public static function safe_strlen( $string ) {

		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $string );
		} else {
			return strlen( $string );
		}

	}

	public static function safe_substr( $string, $start, $length = null ) {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $string, $start, $length );
		} else {
			return substr( $string, $start, $length );
		}
	}

	/**
	 * Reliably compare floats.
	 *
	 * @param  float  $float1
	 * @param  float  $float2
	 * @param  string $operator Supports: '<', '<=', '>', '>=', '==', '=', '!='
	 *
	 * @return bool
	 */
	public static function compare_floats( $float1, $float2, $operator ) {

		$epsilon    = 0.00001;
		$is_equal   = abs( floatval( $float1 ) - floatval( $float2 ) ) < $epsilon;
		$is_greater = floatval( $float1 ) > floatval( $float2 );
		$is_less    = floatval( $float1 ) < floatval( $float2 );

		switch ( $operator ) {
			case '<':
				return $is_less;
			case '<=':
				return $is_less || $is_equal;
			case '>' :
				return $is_greater;
			case '>=':
				return $is_greater || $is_equal;
			case '==':
			case '=':
				return $is_equal;
			case '!=':
				return ! $is_equal;
		}

	}

	public static function encrypt( $text ) {
		$use_mcrypt = apply_filters('gform_use_mcrypt', function_exists( 'mcrypt_encrypt' ) );

		if ( $use_mcrypt ){
			$iv_size = mcrypt_get_iv_size( MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB );
			$key = substr( md5( wp_salt( 'nonce' ) ), 0, $iv_size );

			$encrypted_value = trim( base64_encode( mcrypt_encrypt( MCRYPT_RIJNDAEL_256, $key, $text, MCRYPT_MODE_ECB, mcrypt_create_iv( $iv_size, MCRYPT_RAND ) ) ) );
		}
		else{
			global $wpdb;
			$encrypted_value = base64_encode( $wpdb->get_var( $wpdb->prepare('SELECT AES_ENCRYPT(%s, %s) AS data', $text, wp_salt( 'nonce' ) ) ) );
		}

		return $encrypted_value;
	}

	public static function decrypt( $text ) {

		$use_mcrypt = apply_filters('gform_use_mcrypt', function_exists( 'mcrypt_decrypt' ) );

		if ( $use_mcrypt ){
			$iv_size = mcrypt_get_iv_size( MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB );
			$key = substr( md5( wp_salt( 'nonce' ) ), 0, $iv_size );

			$decrypted_value = trim( mcrypt_decrypt( MCRYPT_RIJNDAEL_256, $key, base64_decode( $text ), MCRYPT_MODE_ECB, mcrypt_create_iv( $iv_size, MCRYPT_RAND ) ) );
		}
		else{
			global $wpdb;
			$decrypted_value = $wpdb->get_var( $wpdb->prepare('SELECT AES_DECRYPT(%s, %s) AS data', base64_decode( $text ), wp_salt( 'nonce' ) ) );
		}

		return $decrypted_value;
	}

    public static function esc_like( $value ) {
        global $wpdb;

        if( is_callable( array( $wpdb, 'esc_like' ) ) ) {
            $value = $wpdb->esc_like( $value );
        } else {
            $value = like_escape( $value );
        }

        return $value;
    }

	public static function is_form_editor(){
		$is_form_editor = GFForms::get_page() == 'form_editor' || ( defined( 'DOING_AJAX' ) && DOING_AJAX && in_array( rgpost( 'action' ), array( 'rg_add_field', 'rg_refresh_field_preview', 'rg_duplicate_field', 'rg_delete_field', 'rg_change_input_type' ) ) );
		return apply_filters( 'gform_is_form_editor', $is_form_editor );
	}

	public static function is_entry_detail(){
		$is_entry_detail = GFForms::get_page() == 'entry_detail_edit' || GFForms::get_page() == 'entry_detail' ;
		return apply_filters( 'gform_is_entry_detail', $is_entry_detail );
	}

	public static function is_entry_detail_view(){
		$is_entry_detail_view = GFForms::get_page() == 'entry_detail' ;
		return apply_filters( 'gform_is_entry_detail_view', $is_entry_detail_view );
	}

	public static function is_entry_detail_edit(){
		$is_entry_detail_edit = GFForms::get_page() == 'entry_detail_edit';
		return apply_filters( 'gform_is_entry_detail_edit', $is_entry_detail_edit );
	}

	public static function has_merge_tag( $string ) {
		return preg_match( '/{.+}/', $string );
	}

	public static function get_upload_page_slug() {
		$slug = get_option( 'gform_upload_page_slug' );
		if ( empty( $slug ) ) {
			$slug = substr( str_shuffle( wp_hash( microtime() ) ), 0, 15 );
			update_option( 'gform_upload_page_slug', $slug );
		}

		return $slug;
	}

	/**
	 * Whitelists a value. Returns the value or the first value in the array.
	 *
	 * @param $value
	 * @param $whitelist
	 *
	 * @return mixed
	 */
	public static function whitelist( $value, $whitelist ) {

		if ( ! in_array( $value, $whitelist ) ) {
			$value = $whitelist[0];
		}
		return $value;
	}

	/**
	 * Forces an integer into a range of integers. Returns the value or the minimum if it's outside the range.
	 *
	 * @param $value
	 * @param $min
	 * @param $max
	 *
	 * @return int
	 */
	public static function int_range( $value, $min, $max ) {
		$value = (int) $value;
		$min   = (int) $min;
		$max   = (int) $max;

		return filter_var( $value, FILTER_VALIDATE_INT, array(
			'min_range' => $min,
			'max_range' => $max
		) ) ? $value : $min;
	}

	public static function load_gf_text_domain( $domain ){
		// Initializing translations. Translation files in the WP_LANG_DIR folder have a higher priority.
		global $l10n;
		$locale = apply_filters( 'plugin_locale', get_locale(), 'gravityforms' );
		if ( ! isset( $l10n[$domain] ) ){
			load_textdomain( 'gravityforms', WP_LANG_DIR . '/gravityforms/gravityforms-' . $locale . '.mo' );
			load_plugin_textdomain( 'gravityforms', false, '/gravityforms/languages' );
		}
	}
}

class GFCategoryWalker extends Walker {
	/**
	 * @see   Walker::$tree_type
	 * @since 2.1.0
	 * @var string
	 */
	var $tree_type = 'category';

	/**
	 * @see   Walker::$db_fields
	 * @since 2.1.0
	 * @todo  Decouple this
	 * @var array
	 */
	var $db_fields = array( 'parent' => 'parent', 'id' => 'term_id' );

	/**
	 * @see   Walker::start_el()
	 * @since 2.1.0
	 *
	 * @param string $output   Passed by reference. Used to append additional content.
	 * @param object $object Category data object.
	 * @param int    $depth    Depth of category. Used for padding.
	 * @param array  $args     Uses 'selected' and 'show_count' keys, if they exist.
	 * @param int    $current_object_id
	 */
	function start_el( &$output, $object, $depth = 0, $args = array(), $current_object_id = 0 ) {
		//$pad = str_repeat('&nbsp;', $depth * 3);
		$pad = str_repeat( '&#9472;', $depth );
		if ( ! empty( $pad ) ) {
			$pad .= '&nbsp;';
		}
		$object->name = "{$pad}{$object->name}";
		$output[]     = $object;
	}
}

/**
 *
 * Notes:
 * 1. The WordPress Transients API does not support boolean
 * values so boolean values should be converted to integers
 * or arrays before setting the values as persistent.
 *
 * 2. The transients API only deletes the transient from the database
 * when the transient is accessed after it has expired. WordPress doesn't
 * do any garbage collection of transients.
 *
 */
class GFCache {
	private static $_transient_prefix = 'GFCache_';
	private static $_cache = array();

	public static function get( $key, &$found = null ) {
		global $blog_id;
		if ( is_multisite() ) {
			$key = $blog_id . ':' . $key;
		}

		if ( isset( self::$_cache[ $key ] ) ) {
			$found = true;
			$data  = rgar( self::$_cache[ $key ], 'data' );

			return $data;
		}

		$data = self::get_transient( $key );

		if ( false === ( $data ) ) {
			$found = false;

			return false;
		} else {
			self::$_cache[ $key ] = array( 'data' => $data, 'is_persistent' => true );
			$found              = true;

			return $data;
		}

	}

	public static function set( $key, $data, $is_persistent = false, $expiration = 0 ) {
		global $blog_id;
		$success = true;

		if ( is_multisite() ) {
			$key = $blog_id . ':' . $key;
		}

		if ( $is_persistent ) {
			$success = self::set_transient( $key, $data, $expiration );
		}

		self::$_cache[ $key ] = array( 'data' => $data, 'is_persistent' => $is_persistent );

		return $success;
	}

	public static function delete( $key ) {
		global $blog_id;
		$success = true;

		if ( is_multisite() ) {
			$key = $blog_id . ':' . $key;
		}

		if ( isset( self::$_cache[ $key ] ) ) {
			if ( self::$_cache[ $key ]['is_persistent'] ) {
				$success = self::delete_transient( $key );
			}

			unset( self::$_cache[ $key ] );
		} else {
			$success = self::delete_transient( $key );

		}

		return $success;
	}

	public static function flush( $flush_persistent = false ) {
		global $wpdb;

		self::$_cache = array();

		if ( false === $flush_persistent ) {
			return true;
		}

		if ( is_multisite() ) {
			$sql = "
                 DELETE FROM $wpdb->sitemeta
                 WHERE meta_key LIKE '_site_transient_timeout_GFCache_%' OR
                 meta_key LIKE '_site_transient_GFCache_%'
                ";
		} else {
			$sql = "
                 DELETE FROM $wpdb->options
                 WHERE option_name LIKE '_transient_timeout_GFCache_%' OR
                 option_name LIKE '_transient_GFCache_%'
                ";

		}
		$rows_deleted = $wpdb->query( $sql );

		$success = $rows_deleted !== false ? true : false;

		return $success;
	}

	private static function delete_transient( $key ) {
		$key = self::$_transient_prefix . wp_hash( $key );
		if ( is_multisite() ) {
			$success = delete_site_transient( $key );
		} else {
			$success = delete_transient( $key );
		}

		return $success;
	}

	private static function set_transient( $key, $data, $expiration ) {
		$key = self::$_transient_prefix . wp_hash( $key );
		if ( is_multisite() ) {
			$success = set_site_transient( $key, $data, $expiration );
		} else {
			$success = set_transient( $key, $data, $expiration );
		}

		return $success;
	}

	private static function get_transient( $key ) {
		$key = self::$_transient_prefix . wp_hash( $key );
		if ( is_multisite() ) {
			$data = get_site_transient( $key );
		} else {
			$data = get_transient( $key );
		}

		return $data;
	}

}