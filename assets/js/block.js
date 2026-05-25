/* global wp */
( function ( blocks, element, blockEditor, components, serverSideRender, i18n ) {
	'use strict';

	var el                = element.createElement;
	var __                = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody         = components.PanelBody;
	var TextareaControl   = components.TextareaControl;
	var TextControl       = components.TextControl;
	var SelectControl     = components.SelectControl;
	var ToggleControl     = components.ToggleControl;
	var RangeControl      = components.RangeControl;

	blocks.registerBlockType( 'curated-rss-aggregator/feed', {
		edit: function ( props ) {
			var atts = props.attributes;
			var set  = props.setAttributes;

			return [
				el(
					InspectorControls,
					{ key: 'controls' },

					/* ── Feed Settings ─────────────────────────────── */
					el(
						PanelBody,
						{
							title: __( 'Feed Settings', 'curated-rss-aggregator' ),
							initialOpen: true,
						},
						el( TextareaControl, {
							label: __( 'Feed URLs (one per line)', 'curated-rss-aggregator' ),
							value: atts.feeds,
							onChange: function ( v ) { set( { feeds: v } ); },
							help: __( 'Leave blank to use the plugin default feeds.', 'curated-rss-aggregator' ),
							rows: 4,
						} ),
						el( RangeControl, {
							label: __( 'Items', 'curated-rss-aggregator' ),
							value: atts.items,
							onChange: function ( v ) { set( { items: v } ); },
							min: 1,
							max: 24,
						} ),
						el( TextControl, {
							label: __( 'Max per feed', 'curated-rss-aggregator' ),
							type: 'number',
							value: String( atts.per_feed ),
							onChange: function ( v ) { set( { per_feed: Math.max( 0, parseInt( v, 10 ) || 0 ) } ); },
							min: '0',
							help: __( '0 = no limit. Prevents one active feed from filling all slots.', 'curated-rss-aggregator' ),
						} )
					),

					/* ── Style ─────────────────────────────────────── */
					el(
						PanelBody,
						{
							title: __( 'Style', 'curated-rss-aggregator' ),
							initialOpen: true,
						},
						el( SelectControl, {
							label: __( 'Layout', 'curated-rss-aggregator' ),
							value: atts.layout,
							options: [
								{ label: __( 'Grid', 'curated-rss-aggregator' ), value: 'grid' },
								{ label: __( 'List', 'curated-rss-aggregator' ), value: 'list' },
								{ label: __( 'Compact', 'curated-rss-aggregator' ), value: 'compact' },
							],
							onChange: function ( v ) { set( { layout: v } ); },
						} ),
						atts.layout === 'grid' && el( RangeControl, {
							label: __( 'Columns (0 = auto)', 'curated-rss-aggregator' ),
							value: atts.columns,
							onChange: function ( v ) { set( { columns: v } ); },
							min: 0,
							max: 6,
							help: __( '0 lets the grid fit as many columns as the space allows.', 'curated-rss-aggregator' ),
						} ),
						el( SelectControl, {
							label: __( 'Card style', 'curated-rss-aggregator' ),
							value: atts.card_style,
							options: [
								{ label: __( 'Default (border)', 'curated-rss-aggregator' ), value: 'default' },
								{ label: __( 'Shadow', 'curated-rss-aggregator' ), value: 'shadow' },
								{ label: __( 'Flat', 'curated-rss-aggregator' ), value: 'flat' },
								{ label: __( 'Outline', 'curated-rss-aggregator' ), value: 'outline' },
								{ label: __( 'None', 'curated-rss-aggregator' ), value: 'none' },
							],
							onChange: function ( v ) { set( { card_style: v } ); },
						} ),
						atts.show_image && el( SelectControl, {
							label: __( 'Image ratio', 'curated-rss-aggregator' ),
							value: atts.image_ratio,
							options: [
								{ label: '16 : 9', value: '16-9' },
								{ label: '3 : 2',  value: '3-2' },
								{ label: '4 : 3',  value: '4-3' },
								{ label: '1 : 1',  value: '1-1' },
							],
							onChange: function ( v ) { set( { image_ratio: v } ); },
						} )
					),

					/* ── Display ───────────────────────────────────── */
					el(
						PanelBody,
						{
							title: __( 'Display', 'curated-rss-aggregator' ),
							initialOpen: true,
						},
						el( ToggleControl, {
							label: __( 'Show image', 'curated-rss-aggregator' ),
							checked: atts.show_image,
							onChange: function ( v ) { set( { show_image: v } ); },
						} ),
						el( ToggleControl, {
							label: __( 'Show date', 'curated-rss-aggregator' ),
							checked: atts.show_date,
							onChange: function ( v ) { set( { show_date: v } ); },
						} ),
						el( ToggleControl, {
							label: __( 'Show source', 'curated-rss-aggregator' ),
							checked: atts.show_source,
							onChange: function ( v ) { set( { show_source: v } ); },
							help: __( 'Displays the feed\'s domain name.', 'curated-rss-aggregator' ),
						} ),
						el( ToggleControl, {
							label: __( 'Show author', 'curated-rss-aggregator' ),
							checked: atts.show_author,
							onChange: function ( v ) { set( { show_author: v } ); },
						} ),
						el( ToggleControl, {
							label: __( 'Show excerpt', 'curated-rss-aggregator' ),
							checked: atts.show_excerpt,
							onChange: function ( v ) { set( { show_excerpt: v } ); },
						} ),
						atts.show_excerpt && el( TextControl, {
							label: __( 'Max excerpt characters', 'curated-rss-aggregator' ),
							type: 'number',
							value: String( atts.max_chars ),
							onChange: function ( v ) { set( { max_chars: Math.max( 0, parseInt( v, 10 ) || 0 ) } ); },
							min: '0',
							help: __( '0 = no limit', 'curated-rss-aggregator' ),
						} ),
						el( ToggleControl, {
							label: __( 'Show "Read more" link', 'curated-rss-aggregator' ),
							checked: atts.show_read_more,
							onChange: function ( v ) { set( { show_read_more: v } ); },
						} ),
						atts.show_read_more && el( TextControl, {
							label: __( 'Read more label', 'curated-rss-aggregator' ),
							value: atts.read_more_text,
							onChange: function ( v ) { set( { read_more_text: v } ); },
							placeholder: __( 'Read more', 'curated-rss-aggregator' ),
						} )
					),

					/* ── Keyword Filters ────────────────────────────── */
					el(
						PanelBody,
						{
							title: __( 'Keyword Filters', 'curated-rss-aggregator' ),
							initialOpen: false,
						},
						el( TextControl, {
							label: __( 'Include keywords', 'curated-rss-aggregator' ),
							value: atts.include_keywords,
							onChange: function ( v ) { set( { include_keywords: v } ); },
							help: __( 'Comma-separated. Item must match at least one.', 'curated-rss-aggregator' ),
						} ),
						el( TextControl, {
							label: __( 'Exclude keywords', 'curated-rss-aggregator' ),
							value: atts.exclude_keywords,
							onChange: function ( v ) { set( { exclude_keywords: v } ); },
							help: __( 'Comma-separated. Items containing any of these are hidden.', 'curated-rss-aggregator' ),
						} )
					)
				),

				el( serverSideRender, {
					key: 'preview',
					block: 'curated-rss-aggregator/feed',
					attributes: atts,
				} ),
			];
		},

		save: function () {
			return null; // rendered server-side
		},
	} );
}(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.serverSideRender,
	window.wp.i18n
) );
