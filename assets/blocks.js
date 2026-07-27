/**
 * Editor UI for the Comms.Church Groups blocks.
 *
 * No build step by design — plain wp.element.createElement, no JSX. Rendering is
 * server-side (ServerSideRender), so the editor preview is the real front-end output
 * rather than a second implementation that drifts out of sync.
 */
( function ( blocks, element, components, blockEditor, i18n, ServerSideRenderPkg ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var SelectControl = components.SelectControl;
	var RangeControl = components.RangeControl;
	var ToggleControl = components.ToggleControl;
	var Notice = components.Notice;
	var ServerSideRender = ServerSideRenderPkg && ServerSideRenderPkg.default
		? ServerSideRenderPkg.default
		: ServerSideRenderPkg;

	var data = window.CCGRPBlocks || { groups: [], types: [], configured: false, settingsUrl: '#' };

	/** Shown in the sidebar when PCO isn't connected yet. */
	function setupNotice() {
		if ( data.configured ) return null;
		return el(
			Notice,
			{ status: 'warning', isDismissible: false },
			el( 'span', {}, __( 'Planning Center is not connected. ', 'comms-church-groups' ) ),
			el( 'a', { href: data.settingsUrl }, __( 'Open settings', 'comms-church-groups' ) )
		);
	}

	/** Shared show/hide toggles. */
	function fieldToggles( props ) {
		var a = props.attributes;
		var set = props.setAttributes;
		return [
			el( ToggleControl, {
				key: 'img',
				label: __( 'Show image', 'comms-church-groups' ),
				checked: !! a.showImage,
				onChange: function ( v ) { set( { showImage: v } ); },
			} ),
			el( ToggleControl, {
				key: 'sch',
				label: __( 'Show schedule', 'comms-church-groups' ),
				checked: !! a.showSchedule,
				onChange: function ( v ) { set( { showSchedule: v } ); },
			} ),
			el( ToggleControl, {
				key: 'loc',
				label: __( 'Show location', 'comms-church-groups' ),
				checked: !! a.showLocation,
				onChange: function ( v ) { set( { showLocation: v } ); },
			} ),
		];
	}

	// ---- Groups Finder -------------------------------------------------------

	blocks.registerBlockType( 'comms-church-groups/groups-list', {
		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;

			var typeOptions = [ { label: __( 'All types', 'comms-church-groups' ), value: '' } ];
			( data.types || [] ).forEach( function ( t ) {
				typeOptions.push( { label: t, value: t } );
			} );

			return el(
				element.Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Groups', 'comms-church-groups' ), initialOpen: true },
						setupNotice(),
						el( SelectControl, {
							label: __( 'Group type', 'comms-church-groups' ),
							value: a.type,
							options: typeOptions,
							onChange: function ( v ) { set( { type: v } ); },
							help: __( 'Show only one type of group.', 'comms-church-groups' ),
						} ),
						el( RangeControl, {
							label: __( 'Columns', 'comms-church-groups' ),
							value: a.columns,
							min: 1,
							max: 4,
							onChange: function ( v ) { set( { columns: v } ); },
						} ),
						el( RangeControl, {
							label: __( 'Max groups', 'comms-church-groups' ),
							value: a.limit,
							min: 0,
							max: 60,
							onChange: function ( v ) { set( { limit: v } ); },
							help: __( '0 shows every group.', 'comms-church-groups' ),
						} ),
						el( ToggleControl, {
							label: __( 'Search + type filter', 'comms-church-groups' ),
							checked: !! a.showFilter,
							onChange: function ( v ) { set( { showFilter: v } ); },
						} )
					),
					el(
						PanelBody,
						{ title: __( 'Card fields', 'comms-church-groups' ), initialOpen: false },
						fieldToggles( props ),
						el( TextControl, {
							label: __( 'Empty message', 'comms-church-groups' ),
							value: a.emptyText,
							onChange: function ( v ) { set( { emptyText: v } ); },
							placeholder: __( 'No groups are open right now — check back soon.', 'comms-church-groups' ),
						} )
					)
				),
				el( ServerSideRender, {
					block: 'comms-church-groups/groups-list',
					attributes: a,
				} )
			);
		},
		save: function () { return null; }, // server-rendered
	} );

	// ---- Group Card ----------------------------------------------------------

	blocks.registerBlockType( 'comms-church-groups/group-card', {
		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;

			/* A picker beats asking an editor to find a numeric ID inside Planning
			   Center. Falls back to a plain text field if the list didn't load. */
			var groupControl;
			if ( data.groups && data.groups.length ) {
				var options = [ { label: __( '— Select a group —', 'comms-church-groups' ), value: '' } ];
				data.groups.forEach( function ( g ) {
					options.push( {
						label: g.type ? g.name + ' (' + g.type + ')' : g.name,
						value: String( g.id ),
					} );
				} );
				groupControl = el( SelectControl, {
					label: __( 'Group', 'comms-church-groups' ),
					value: String( a.groupId || '' ),
					options: options,
					onChange: function ( v ) { set( { groupId: v } ); },
				} );
			} else {
				groupControl = el( TextControl, {
					label: __( 'Group ID', 'comms-church-groups' ),
					value: a.groupId,
					onChange: function ( v ) { set( { groupId: v } ); },
					help: __( 'Connect Planning Center to pick from a list instead.', 'comms-church-groups' ),
				} );
			}

			return el(
				element.Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Group', 'comms-church-groups' ), initialOpen: true },
						setupNotice(),
						groupControl
					),
					el(
						PanelBody,
						{ title: __( 'Card fields', 'comms-church-groups' ), initialOpen: false },
						fieldToggles( props )
					)
				),
				el( ServerSideRender, {
					block: 'comms-church-groups/group-card',
					attributes: a,
				} )
			);
		},
		save: function () { return null; },
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.components,
	window.wp.blockEditor,
	window.wp.i18n,
	window.wp.serverSideRender
);
