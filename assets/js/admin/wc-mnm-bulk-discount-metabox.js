/* global woocommerce_free_gift_coupon_meta_i18n */

/**
 * Script for handling the bulk rule metabox UI.
 */

;( function( $ ) {


	var WC_MNM_Bulk_Discounts = {

		// Model
		this.rule = Backbone.Model.extend({
			defaults: {
					'min': '',
					'max': '',
					'amount' : ''
			}

		});

		
		this.ruleList = Backbone.Collection.extend({
		model: Rule
		});



	};

	/*-----------------------------------------------------------------------------------*/
	/* Execute the above methods in the WC_MNM_Bulk_Discounts object.
	/*-----------------------------------------------------------------------------------*/

	WC_MNM_Bulk_Discounts.initApplication();

	/**
	 * Handle toggle display for discount types.
	 */
    $( '#_mnm_per_product_pricing' ).change( function() {

    	if ( $( '#_mnm_per_product_pricing' ).prop( 'checked' ) ) {

	        if( 'bulk' === $( "#mnm_product_data input.mnm_discount_mode" ).val() ) {
	            $( '.show_if_bulk_discount_mode' ).show();
	            $( '._mnm_per_product_discount_field' ).hide();
	        } else {
	            $( ".show_if_bulk_discount_mode" ).hide();
	            $( '._mnm_per_product_discount_field' ).show();
	        }

	    } else {
	    	$('.hide_if_static_pricing').hide();
	    }

	    $( "#mnm_product_data input.mnm_discount_mode:checked" ).change();

    });

	// Hide show flat/bulk inputs depending on mode.
	$( "#mnm_product_data input.mnm_discount_mode" ).on( 'change', function() {
	    
	    if ( $( '#_mnm_per_product_pricing' ).prop( 'checked' ) ) {
    		if( 'bulk' === $( this ).val() ) {
    			$( "._mnm_per_product_discount_field" ).hide();
    			$( ".show_if_bulk_discount_mode" ).show();
    		} else {
    			$( "._mnm_per_product_discount_field" ).show();
    			$( ".show_if_bulk_discount_mode" ).hide();
    		}
	    } else {
	    	$('.hide_if_static_pricing').hide();
	    }

	} );

	$( "#mnm_product_data input.mnm_discount_mode:checked" ).change();


} ) ( jQuery );