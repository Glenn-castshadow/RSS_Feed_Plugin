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
							label: __( 'Show excerpt', 'curated-rss-aggregator' ),
							checked: atts.show_excerpt,
							onChange: function ( v ) { set( { show_excerpt: v } ); },
						} )
					),
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
