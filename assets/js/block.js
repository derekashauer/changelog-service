/**
 * ChangelogWP Gutenberg Block
 *
 * @package ChangelogWP
 */

( function( blocks, element, blockEditor, components, i18n, serverSideRender ) {
	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var ToggleControl = components.ToggleControl;
	var TextControl = components.TextControl;
	var RangeControl = components.RangeControl;
	var ServerSideRender = serverSideRender;
	var useBlockProps = blockEditor.useBlockProps;

	// Get defaults from PHP (global settings).
	var defaults = ( typeof caasBlock !== 'undefined' && caasBlock.defaults ) ? caasBlock.defaults : {
		showMeta: true,
		limit: 0,
		headingLevel: 4,
		order: 'newest',
		filterBadges: false,
		search: false,
		copyLink: false,
		showEmojis: true,
		badgeStyle: 'rounded',
		badgePosition: 'left',
		signup: false,
		template: 'default'
	};

	// Special value for WordPress.org plugin option.
	var WPORG_VALUE = 'wporg';

	blocks.registerBlockType( 'changelogwp/changelog', {
		title: __( 'Changelog', 'changelog-service' ),
		description: __( 'Display a changelog with version badges.', 'changelog-service' ),
		icon: 'list-view',
		category: 'widgets',
		keywords: [
			__( 'changelog', 'changelog-service' ),
			__( 'version', 'changelog-service' ),
			__( 'updates', 'changelog-service' )
		],
		attributes: {
			changelogId: {
				type: 'number',
				default: 0
			},
			eddId: {
				type: 'number',
				default: 0
			},
			pluginSlug: {
				type: 'string',
				default: ''
			},
			wporgSelected: {
				type: 'boolean',
				default: false
			},
			showMeta: {
				type: 'boolean',
				default: defaults.showMeta
			},
			showEmojis: {
				type: 'boolean',
				default: defaults.showEmojis
			},
			limit: {
				type: 'number',
				default: defaults.limit
			},
			headingLevel: {
				type: 'number',
				default: defaults.headingLevel
			},
			order: {
				type: 'string',
				default: defaults.order
			},
			filterBadges: {
				type: 'boolean',
				default: defaults.filterBadges
			},
			search: {
				type: 'boolean',
				default: defaults.search
			},
			copyLink: {
				type: 'boolean',
				default: defaults.copyLink
			},
			badgeStyle: {
				type: 'string',
				default: defaults.badgeStyle
			},
			badgePosition: {
				type: 'string',
				default: defaults.badgePosition
			},
			signupEnabled: {
				type: 'boolean',
				default: !! defaults.signup
			},
			signupUrl: {
				type: 'string',
				default: ''
			},
			template: {
				type: 'string',
				default: defaults.template || 'default'
			},
		},

		edit: function( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();

			// Determine current source value for the dropdown.
			var getCurrentSourceValue = function() {
				// Check wporgSelected flag OR if pluginSlug has a value
				if ( attributes.wporgSelected || attributes.pluginSlug ) {
					return WPORG_VALUE;
				}
				if ( attributes.eddId > 0 ) {
					return attributes.eddId;
				}
				if ( attributes.changelogId > 0 ) {
					return attributes.changelogId;
				}
				return 0;
			};

			// Check if WordPress.org is selected
			var isWporgSelected = getCurrentSourceValue() === WPORG_VALUE;

			// Check if changelog is configured (any source).
			var hasChangelogSource = attributes.changelogId > 0 || attributes.eddId > 0 || ( attributes.pluginSlug && attributes.pluginSlug.trim() !== '' );

			// Build the inspector controls elements array
			var inspectorElements = [];

			// Changelog Source dropdown
			inspectorElements.push(
				el( SelectControl, {
					key: 'source-select',
					label: __( 'Changelog Source', 'changelog-service' ),
					value: getCurrentSourceValue(),
					options: ( function() {
						var options = [];

						// Add changelogs, filtering out any invalid entries
						if ( window.caasBlock.changelogs && Array.isArray( window.caasBlock.changelogs ) ) {
							options = window.caasBlock.changelogs.filter( function( option ) {
								return option &&
								       option.value !== undefined &&
								       option.value !== null &&
								       option.label !== undefined &&
								       option.label !== null &&
								       String( option.label ).trim() !== '';
							} );
						}

						// Add EDD products if available
						if ( window.caasBlock.eddSlActive && window.caasBlock.eddProducts && Array.isArray( window.caasBlock.eddProducts ) && window.caasBlock.eddProducts.length > 0 ) {
							// Filter out invalid EDD products
							var validEddProducts = window.caasBlock.eddProducts.filter( function( option ) {
								return option &&
								       option.value !== undefined &&
								       option.value !== null &&
								       option.label !== undefined &&
								       option.label !== null &&
								       String( option.label ).trim() !== '';
							} );

							// Add valid EDD products directly
							if ( validEddProducts.length > 0 ) {
								options = options.concat( validEddProducts );
							}
						}

						// Add WordPress.org Plugin option if enabled
						if ( window.caasBlock.wporgEnabled ) {
							options.push( {
								value: WPORG_VALUE,
								label: __( 'WordPress.org Plugin', 'changelog-service' )
							} );
						}

						return options;
					} )(),
					onChange: function( value ) {
						// Handle WordPress.org Plugin selection
						if ( value === WPORG_VALUE ) {
							setAttributes( {
								changelogId: 0,
								eddId: 0,
								wporgSelected: true
							} );
							return;
						}

						var parsedValue = parseInt( value, 10 );
						if ( isNaN( parsedValue ) || parsedValue <= 0 ) {
							setAttributes( {
								changelogId: 0,
								eddId: 0,
								pluginSlug: '',
								wporgSelected: false
							} );
							return;
						}

						// Check if this is an EDD product by checking if it's in the EDD products array
						var isEddProduct = false;
						if ( window.caasBlock.eddProducts ) {
							for ( var i = 0; i < window.caasBlock.eddProducts.length; i++ ) {
								if ( window.caasBlock.eddProducts[i].value === parsedValue ) {
									isEddProduct = true;
									break;
								}
							}
						}

						if ( isEddProduct ) {
							setAttributes( {
								eddId: parsedValue,
								changelogId: 0,
								pluginSlug: '',
								wporgSelected: false
							} );
						} else {
							setAttributes( {
								changelogId: parsedValue,
								eddId: 0,
								pluginSlug: '',
								wporgSelected: false
							} );
						}
					}
				} )
			);

			// Show WordPress.org plugin slug input when that option is selected
			if ( isWporgSelected ) {
				inspectorElements.push(
					el( TextControl, {
						key: 'plugin-slug-input',
						label: __( 'Plugin Slug', 'changelog-service' ),
						help: __( 'Enter the WordPress.org plugin slug (e.g., "woocommerce", "confetti")', 'changelog-service' ),
						value: attributes.pluginSlug || '',
						onChange: function( value ) {
							setAttributes( { pluginSlug: value.toLowerCase().replace( /[^a-z0-9-]/g, '' ) } );
						},
						placeholder: 'woocommerce'
					} )
				);
			}

			// Template selector
			inspectorElements.push(
				el( SelectControl, {
					key: 'template-select',
					label: __( 'Template', 'changelog-service' ),
					help: __( 'Visual layout for the changelog', 'changelog-service' ),
					value: attributes.template || 'default',
					options: [
						{ value: 'default', label: __( 'Default', 'changelog-service' ) },
						{ value: 'timeline', label: __( 'Timeline', 'changelog-service' ) }
					],
					onChange: function( value ) {
						setAttributes( { template: value } );
					}
				} )
			);

			// Add all the toggle controls
			inspectorElements.push(
				el( ToggleControl, {
					key: 'show-meta-toggle',
					label: __( 'Show Meta Information', 'changelog-service' ),
					help: __( 'Display version, requirements, etc.', 'changelog-service' ),
					checked: attributes.showMeta,
					onChange: function( value ) {
						setAttributes( { showMeta: value } );
					}
				} ),
				el( ToggleControl, {
					key: 'filter-badges-toggle',
					label: __( 'Filter Badges', 'changelog-service' ),
					help: __( 'Show filter buttons for badge types', 'changelog-service' ),
					checked: attributes.filterBadges || false,
					onChange: function( value ) {
						setAttributes( { filterBadges: value } );
					}
				} ),
				el( ToggleControl, {
					key: 'search-toggle',
					label: __( 'Search', 'changelog-service' ),
					help: __( 'Enable search functionality', 'changelog-service' ),
					checked: attributes.search || false,
					onChange: function( value ) {
						setAttributes( { search: value } );
					}
				} ),
				el( ToggleControl, {
					key: 'copy-link-toggle',
					label: __( 'Copy Link Button', 'changelog-service' ),
					help: __( 'Show copy link button for each version', 'changelog-service' ),
					checked: attributes.copyLink || false,
					onChange: function( value ) {
						setAttributes( { copyLink: value } );
					}
				} ),
				el( ToggleControl, {
					key: 'show-emojis-toggle',
					label: __( 'Show Emojis', 'changelog-service' ),
					help: __( 'Display emojis in badge labels', 'changelog-service' ),
					checked: attributes.showEmojis !== undefined ? attributes.showEmojis : true,
					onChange: function( value ) {
						setAttributes( { showEmojis: value } );
					}
				} ),
				el( RangeControl, {
					key: 'limit-range',
					label: __( 'Limit Versions', 'changelog-service' ),
					help: __( '0 = show all versions', 'changelog-service' ),
					value: attributes.limit,
					onChange: function( value ) {
						setAttributes( { limit: value } );
					},
					min: 0,
					max: 50
				} ),
				el( SelectControl, {
					key: 'heading-level-select',
					label: __( 'Heading Level', 'changelog-service' ),
					help: __( 'Heading level for version numbers', 'changelog-service' ),
					value: attributes.headingLevel || 4,
					options: [
						{ value: 1, label: 'H1' },
						{ value: 2, label: 'H2' },
						{ value: 3, label: 'H3' },
						{ value: 4, label: 'H4' },
						{ value: 5, label: 'H5' },
						{ value: 6, label: 'H6' }
					],
					onChange: function( value ) {
						var level = parseInt( value, 10 );
						if ( level >= 1 && level <= 6 ) {
							setAttributes( { headingLevel: level } );
						}
					}
				} ),
				el( SelectControl, {
					key: 'order-select',
					label: __( 'Order', 'changelog-service' ),
					help: __( 'Display order of versions', 'changelog-service' ),
					value: attributes.order || 'newest',
					options: [
						{ value: 'newest', label: __( 'Newest First', 'changelog-service' ) },
						{ value: 'oldest', label: __( 'Oldest First', 'changelog-service' ) }
					],
					onChange: function( value ) {
						setAttributes( { order: value } );
					}
				} ),
				el( SelectControl, {
					key: 'badge-style-select',
					label: __( 'Badge Style', 'changelog-service' ),
					help: __( 'Style of badge appearance', 'changelog-service' ),
					value: attributes.badgeStyle || 'rounded',
					options: [
						{ value: 'rounded', label: __( 'Rounded', 'changelog-service' ) },
						{ value: 'square', label: __( 'Square', 'changelog-service' ) },
						{ value: 'pill', label: __( 'Pill', 'changelog-service' ) }
					],
					onChange: function( value ) {
						setAttributes( { badgeStyle: value } );
					}
				} ),
				el( SelectControl, {
					key: 'badge-position-select',
					label: __( 'Badge Position', 'changelog-service' ),
					help: __( 'Position of badges relative to text', 'changelog-service' ),
					value: attributes.badgePosition || 'left',
					options: [
						{ value: 'left', label: __( 'Left (fixed column)', 'changelog-service' ) },
						{ value: 'inline', label: __( 'Inline (before text)', 'changelog-service' ) }
					],
					onChange: function( value ) {
						setAttributes( { badgePosition: value } );
					}
				} ),
				el( ToggleControl, {
					key: 'signup-enabled-toggle',
					label: __( 'Show Signup Link', 'changelog-service' ),
					help: isWporgSelected
						? __( 'Shows "Get notified of updates" link (auto-generates URL from plugin slug)', 'changelog-service' )
						: __( 'Shows "Get notified of updates" link to ChangelogWP.com', 'changelog-service' ),
					checked: attributes.signupEnabled || false,
					onChange: function( value ) {
						setAttributes( { signupEnabled: value } );
					}
				} )
			);

			// Show signup URL field when enabled and NOT using WordPress.org source
			if ( attributes.signupEnabled && ! isWporgSelected ) {
				inspectorElements.push(
					el( TextControl, {
						key: 'signup-url-input',
						label: __( 'Signup URL', 'changelog-service' ),
						help: __( 'Enter the URL for the signup link (e.g., https://changelogwp.com/plugin/your-plugin/)', 'changelog-service' ),
						value: attributes.signupUrl || '',
						onChange: function( value ) {
							setAttributes( { signupUrl: value } );
						},
						placeholder: 'https://changelogwp.com/plugin/...'
					} )
				);
			}

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{
							title: __( 'Changelog Settings', 'changelog-service' ),
							initialOpen: true
						},
						inspectorElements
					)
				),
				hasChangelogSource
					? el( ServerSideRender, {
						block: 'changelogwp/changelog',
						attributes: attributes,
						key: JSON.stringify( attributes )
					} )
					: el(
						'div',
						{
							className: 'caas-block-placeholder',
							style: {
								padding: '20px',
								backgroundColor: '#f0f0f1',
								border: '1px dashed #c3c4c7',
								borderRadius: '4px',
								textAlign: 'center'
							}
						},
						el( 'p', {}, __( 'Select a changelog from the block settings to display it here.', 'changelog-service' ) )
					)
			);
		},

		save: function() {
			// Server-side rendered.
			return null;
		}
	} );

} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n,
	window.wp.serverSideRender
);
