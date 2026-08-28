/**
 * WHMCS Connector — Shared Gutenberg Block Editor Components & Data Provider.
 *
 * Provides: ProductPicker, PlanRepeater, FeaturesEditor, CycleNotice, SkeletonLoader, EmptyState.
 */
( function () {
	'use strict';

	var el = window.wp.element.createElement;
	var useState = window.wp.element.useState;
	var useEffect = window.wp.element.useEffect;
	var useCallback = window.wp.element.useCallback;
	var SelectControl = window.wp.components.SelectControl;
	var TextControl = window.wp.components.TextControl;
	var Button = window.wp.components.Button;
	var PanelBody = window.wp.components.PanelBody;
	var Notice = window.wp.components.Notice;
	var Spinner = window.wp.components.Spinner;
	var apiFetch = window.wp.apiFetch;
	var __ = window.wp.i18n.__;

	// In-memory cache for editor product queries.
	var productsCache = null;
	var groupsCache = null;
	var fetchPromise = null;

	/**
	 * Hook: Fetch and cache WHMCS products & groups via REST API.
	 */
	function useWhmcsProducts() {
		var state = useState( {
			products: productsCache || [],
			groups: groupsCache || [],
			loading: ! productsCache,
			error: null,
		} );
		var data = state[0];
		var setData = state[1];

		var loadData = useCallback( function ( force ) {
			if ( ! force && productsCache && groupsCache ) {
				setData( { products: productsCache, groups: groupsCache, loading: false, error: null } );
				return;
			}

			setData( function ( prev ) {
				return Object.assign( {}, prev, { loading: true, error: null } );
			} );

			var productsReq = apiFetch( { path: '/whmcs-connector/v1/products' } );
			var groupsReq = apiFetch( { path: '/whmcs-connector/v1/product-groups' } );

			Promise.all( [ productsReq, groupsReq ] )
				.then( function ( res ) {
					var prods = ( res[0] && res[0].products ) || [];
					var grps = ( res[1] && res[1].groups ) || [];
					productsCache = prods;
					groupsCache = grps;
					setData( { products: prods, groups: grps, loading: false, error: null } );
				} )
				.catch( function ( err ) {
					setData( {
						products: [],
						groups: [],
						loading: false,
						error: ( err && err.message ) || __( 'Failed to load WHMCS products.', 'whmcs-connector' ),
					} );
				} );
		}, [] );

		useEffect( function () {
			loadData( false );
		}, [ loadData ] );

		return {
			products: data.products,
			groups: data.groups,
			loading: data.loading,
			error: data.error,
			refresh: function () {
				loadData( true );
			},
		};
	}

	/**
	 * Component: ProductPicker
	 * Searchable, group-scoped WHMCS product selector.
	 */
	function ProductPicker( props ) {
		var selectedPid = String( props.selectedPid || '' );
		var onChange = props.onChange;
		var label = props.label || __( 'Select WHMCS Product', 'whmcs-connector' );
		var help = props.help || '';
		var products = props.products || [];
		var groups = props.groups || [];
		var loading = props.loading;

		var gidState = useState( '' );
		var selectedGid = gidState[0];
		var setSelectedGid = gidState[1];

		var searchState = useState( '' );
		var searchQuery = searchState[0];
		var setSearchQuery = searchState[1];

		if ( loading ) {
			return el(
				'div',
				{ style: { padding: '8px 0', display: 'flex', alignItems: 'center', gap: '8px' } },
				el( Spinner, null ),
				el( 'span', null, __( 'Loading WHMCS products...', 'whmcs-connector' ) )
			);
		}

		// Filter products by selected group and search query
		var filtered = products.filter( function ( p ) {
			if ( selectedGid && String( p.gid ) !== String( selectedGid ) ) {
				return false;
			}
			if ( searchQuery ) {
				var q = searchQuery.toLowerCase();
				var matchName = p.name && p.name.toLowerCase().indexOf( q ) !== -1;
				var matchPid = String( p.pid ).indexOf( q ) !== -1;
				var matchDesc = p.description && p.description.toLowerCase().indexOf( q ) !== -1;
				return matchName || matchPid || matchDesc;
			}
			return true;
		} );

		// Check if selectedPid exists in loaded products
		var currentProduct = products.find( function ( p ) {
			return String( p.pid ) === selectedPid;
		} );
		var isMissing = selectedPid && ! currentProduct && products.length > 0;

		var groupOptions = [ { label: __( '— All Product Groups —', 'whmcs-connector' ), value: '' } ].concat(
			groups.map( function ( g ) {
				return { label: g.name || ( 'Group #' + g.id ), value: String( g.id ) };
			} )
		);

		var productOptions = [ { label: __( '— Choose a Product —', 'whmcs-connector' ), value: '' } ].concat(
			filtered.map( function ( p ) {
				var priceSummary = p.pricing && p.pricing.monthly ? ' (' + p.pricing.monthly + '/mo)' : ( p.pricing && p.pricing.annually ? ' (' + p.pricing.annually + '/yr)' : '' );
				return {
					label: '#' + p.pid + ' — ' + p.name + priceSummary,
					value: String( p.pid ),
				};
			} )
		);

		// If currentProduct is from another group not in filtered, include it in dropdown so it doesn't break
		if ( currentProduct && ! filtered.some( function ( p ) { return String( p.pid ) === selectedPid; } ) ) {
			productOptions.push( {
				label: '#' + currentProduct.pid + ' — ' + currentProduct.name,
				value: String( currentProduct.pid ),
			} );
		}

		return el(
			'div',
			{ className: 'whmcs-product-picker-container', style: { marginBottom: '16px' } },
			groups.length > 1 && el( SelectControl, {
				label: __( 'Filter by Group', 'whmcs-connector' ),
				value: selectedGid,
				options: groupOptions,
				onChange: function ( val ) {
					setSelectedGid( val );
				},
			} ),
			products.length > 6 && el( TextControl, {
				label: __( 'Search Products', 'whmcs-connector' ),
				placeholder: __( 'Type name or PID...', 'whmcs-connector' ),
				value: searchQuery,
				onChange: function ( val ) {
					setSearchQuery( val );
				},
			} ),
			el( SelectControl, {
				label: label,
				help: help,
				value: selectedPid,
				options: productOptions,
				onChange: function ( val ) {
					var chosen = products.find( function ( p ) { return String( p.pid ) === String( val ); } );
					onChange( val, chosen );
				},
			} ),
			isMissing && el(
				Notice,
				{ status: 'warning', isDismissible: false },
				__( '⚠️ Product #' + selectedPid + ' no longer exists or is disabled in WHMCS. Please pick a different product.', 'whmcs-connector' )
			)
		);
	}

	/**
	 * Component: FeaturesEditor
	 * Custom features list editor with add, remove, reorder, and reset capabilities.
	 */
	function FeaturesEditor( props ) {
		var features = Array.isArray( props.features ) ? props.features : [];
		var onChange = props.onChange;
		var defaultFeatures = props.defaultFeatures || [];

		function updateItem( index, val ) {
			var copy = features.slice();
			copy[ index ] = val;
			onChange( copy );
		}

		function removeItem( index ) {
			var copy = features.slice();
			copy.splice( index, 1 );
			onChange( copy );
		}

		function addItem() {
			var copy = features.concat( [ __( 'New Feature', 'whmcs-connector' ) ] );
			onChange( copy );
		}

		function moveItem( fromIndex, toIndex ) {
			if ( toIndex < 0 || toIndex >= features.length ) {
				return;
			}
			var copy = features.slice();
			var item = copy.splice( fromIndex, 1 )[0];
			copy.splice( toIndex, 0, item );
			onChange( copy );
		}

		function resetToDescription() {
			if ( defaultFeatures && defaultFeatures.length > 0 ) {
				onChange( defaultFeatures.slice() );
			}
		}

		return el(
			'div',
			{ className: 'whmcs-features-editor', style: { marginTop: '12px', marginBottom: '16px' } },
			el( 'label', { className: 'components-base-control__label', style: { display: 'block', fontWeight: 600, marginBottom: '8px' } }, __( 'Feature Bullets', 'whmcs-connector' ) ),
			features.map( function ( feat, idx ) {
				return el(
					'div',
					{
						key: idx,
						style: {
							display: 'flex',
							alignItems: 'center',
							gap: '4px',
							marginBottom: '6px',
						},
					},
					el( TextControl, {
						value: feat,
						style: { flex: 1, marginBottom: 0 },
						placeholder: __( 'Feature item (e.g. !Disabled feature or ✓ Free SSL)', 'whmcs-connector' ),
						onChange: function ( val ) {
							updateItem( idx, val );
						},
					} ),
					el( Button, {
						icon: 'arrow-up-alt2',
						isSmall: true,
						disabled: idx === 0,
						label: __( 'Move Up', 'whmcs-connector' ),
						onClick: function () { moveItem( idx, idx - 1 ); },
					} ),
					el( Button, {
						icon: 'arrow-down-alt2',
						isSmall: true,
						disabled: idx === features.length - 1,
						label: __( 'Move Down', 'whmcs-connector' ),
						onClick: function () { moveItem( idx, idx + 1 ); },
					} ),
					el( Button, {
						icon: 'no-alt',
						isSmall: true,
						isDestructive: true,
						label: __( 'Remove', 'whmcs-connector' ),
						onClick: function () { removeItem( idx ); },
					} )
				);
			} ),
			el(
				'div',
				{ style: { display: 'flex', gap: '8px', marginTop: '8px' } },
				el(
					Button,
					{
						variant: 'secondary',
						isSmall: true,
						icon: 'plus',
						onClick: addItem,
					},
					__( 'Add Feature', 'whmcs-connector' )
				),
				defaultFeatures && defaultFeatures.length > 0 && el(
					Button,
					{
						variant: 'tertiary',
						isSmall: true,
						icon: 'update',
						onClick: resetToDescription,
					},
					__( 'Reset from WHMCS', 'whmcs-connector' )
				)
			)
		);
	}

	/**
	 * Compute cycle intersection across selected products.
	 */
	function getCycleIntersection( selectedPids, products ) {
		if ( ! selectedPids || selectedPids.length === 0 ) {
			return [];
		}

		var productObjects = selectedPids.map( function ( pid ) {
			return products.find( function ( p ) { return String( p.pid ) === String( pid ); } );
		} ).filter( Boolean );

		if ( productObjects.length === 0 ) {
			return [];
		}

		var intersection = ( productObjects[0].available_cycles || [] ).slice();

		for ( var i = 1; i < productObjects.length; i++ ) {
			var itemCycles = productObjects[i].available_cycles || [];
			intersection = intersection.filter( function ( c ) {
				return itemCycles.indexOf( c ) !== -1;
			} );
		}

		return intersection;
	}

	/**
	 * Component: CycleIntersectionNotice
	 * Warns when selected products have mismatched available billing cycles.
	 */
	function CycleIntersectionNotice( props ) {
		var selectedPids = props.selectedPids || [];
		var products = props.products || [];
		var intersection = getCycleIntersection( selectedPids, products );

		var productObjects = selectedPids.map( function ( pid ) {
			return products.find( function ( p ) { return String( p.pid ) === String( pid ); } );
		} ).filter( Boolean );

		if ( productObjects.length <= 1 ) {
			return null;
		}

		var hasMismatch = false;
		for ( var i = 0; i < productObjects.length; i++ ) {
			var cycles = productObjects[i].available_cycles || [];
			if ( cycles.length !== intersection.length ) {
				hasMismatch = true;
				break;
			}
		}

		if ( ! hasMismatch ) {
			return null;
		}

		if ( intersection.length === 0 ) {
			return el(
				Notice,
				{ status: 'error', isDismissible: false, style: { marginBottom: '12px' } },
				__( '⚠️ No common billing cycles available across selected plans.', 'whmcs-connector' )
			);
		}

		var labels = intersection.map( function ( c ) {
			return c.charAt( 0 ).toUpperCase() + c.slice( 1 );
		} ).join( ', ' );

		return el(
			Notice,
			{ status: 'warning', isDismissible: false, style: { marginBottom: '12px' } },
			__( 'Not all selected plans offer the same billing cycles — the cycle toggle will only show mutually available cycles: ' + labels, 'whmcs-connector' )
		);
	}

	/**
	 * Component: PlanRepeater
	 * Drag/Button reorderable plan repeater for SaaS & Featured Hosting blocks.
	 */
	function PlanRepeater( props ) {
		var plans = Array.isArray( props.plans ) ? props.plans : [];
		var onChange = props.onChange;
		var products = props.products || [];
		var groups = props.groups || [];
		var loading = props.loading;
		var renderPlanSettings = props.renderPlanSettings; // (plan, index, updatePlan) => ReactNode

		function addPlan() {
			var availableProduct = products.find( function ( p ) {
				return ! plans.some( function ( pl ) { return String( pl.pid ) === String( p.pid ); } );
			} ) || products[0];

			var newPid = availableProduct ? String( availableProduct.pid ) : '1';
			var newTagline = availableProduct ? ( availableProduct.tagline || '' ) : '';
			var newFeatures = availableProduct ? ( availableProduct.features || [] ) : [];

			var newPlan = {
				pid: newPid,
				tagline: newTagline,
				is_popular: false,
				ribbon: '',
				features: newFeatures,
			};

			onChange( plans.concat( [ newPlan ] ) );
		}

		function removePlan( index ) {
			var copy = plans.slice();
			copy.splice( index, 1 );
			onChange( copy );
		}

		function movePlan( fromIndex, toIndex ) {
			if ( toIndex < 0 || toIndex >= plans.length ) {
				return;
			}
			var copy = plans.slice();
			var item = copy.splice( fromIndex, 1 )[0];
			copy.splice( toIndex, 0, item );
			onChange( copy );
		}

		function updatePlan( index, patch ) {
			var copy = plans.slice();
			copy[ index ] = Object.assign( {}, copy[ index ], patch );
			onChange( copy );
		}

		return el(
			'div',
			{ className: 'whmcs-plan-repeater' },
			plans.map( function ( plan, idx ) {
				var prod = products.find( function ( p ) { return String( p.pid ) === String( plan.pid ); } );
				var planTitle = __( 'Plan ', 'whmcs-connector' ) + ( idx + 1 ) + ( prod ? ': ' + prod.name : ' (PID ' + ( plan.pid || '—' ) + ')' );

				return el(
					PanelBody,
					{
						key: idx,
						title: planTitle,
						initialOpen: idx === 0,
					},
					el(
						'div',
						{ style: { display: 'flex', justifyContent: 'flex-end', gap: '4px', marginBottom: '12px' } },
						el( Button, {
							icon: 'arrow-up-alt2',
							isSmall: true,
							disabled: idx === 0,
							label: __( 'Move Up', 'whmcs-connector' ),
							onClick: function () { movePlan( idx, idx - 1 ); },
						} ),
						el( Button, {
							icon: 'arrow-down-alt2',
							isSmall: true,
							disabled: idx === plans.length - 1,
							label: __( 'Move Down', 'whmcs-connector' ),
							onClick: function () { movePlan( idx, idx + 1 ); },
						} ),
						el( Button, {
							icon: 'trash',
							isSmall: true,
							isDestructive: true,
							label: __( 'Remove Plan', 'whmcs-connector' ),
							onClick: function () { removePlan( idx ); },
						} )
					),
					el( ProductPicker, {
						selectedPid: plan.pid,
						products: products,
						groups: groups,
						loading: loading,
						label: __( 'WHMCS Product', 'whmcs-connector' ),
						onChange: function ( val, chosenProd ) {
							var patch = { pid: val };
							// Auto-populate default tagline and features if empty or switched
							if ( chosenProd ) {
								if ( ! plan.tagline && chosenProd.tagline ) {
									patch.tagline = chosenProd.tagline;
								}
								if ( ( ! plan.features || plan.features.length === 0 ) && chosenProd.features ) {
									patch.features = chosenProd.features;
								}
							}
							updatePlan( idx, patch );
						},
					} ),
					el( TextControl, {
						label: __( 'Tagline / Short Description', 'whmcs-connector' ),
						value: plan.tagline || '',
						placeholder: ( prod && prod.tagline ) || __( 'First line of WHMCS product description...', 'whmcs-connector' ),
						help: __( 'Optional marketing tagline override.', 'whmcs-connector' ),
						onChange: function ( val ) {
							updatePlan( idx, { tagline: val } );
						},
					} ),
					renderPlanSettings && renderPlanSettings( plan, idx, function ( patch ) {
						updatePlan( idx, patch );
					}, prod ),
					el( FeaturesEditor, {
						features: plan.features,
						defaultFeatures: ( prod && prod.features ) || [],
						onChange: function ( newFeats ) {
							updatePlan( idx, { features: newFeats } );
						},
					} )
				);
			} ),
			el(
				'div',
				{ style: { padding: '16px 0' } },
				el(
					Button,
					{
						variant: 'primary',
						icon: 'plus',
						style: { width: '100%', justifyContent: 'center' },
						onClick: addPlan,
					},
					__( 'Add Plan', 'whmcs-connector' )
				)
			)
		);
	}

	/**
	 * Component: EmptyState
	 * Displays helpful prompt on block canvas when 0 plans are configured.
	 */
	function EmptyState( props ) {
		var onAdd = props.onAdd;
		var title = props.title || __( 'No Plans Added Yet', 'whmcs-connector' );
		var desc = props.desc || __( 'Click below or use the sidebar inspector to add your first product plan.', 'whmcs-connector' );

		return el(
			'div',
			{
				className: 'whmcs-block-empty-state',
				style: {
					padding: '48px 24px',
					textAlign: 'center',
					background: '#f8fafc',
					border: '2px dashed #cbd5e1',
					borderRadius: '16px',
					margin: '20px 0',
				},
			},
			el( 'div', { style: { fontSize: '32px', marginBottom: '8px' } }, '📦' ),
			el( 'h3', { style: { margin: '0 0 8px', fontSize: '18px', color: '#0f172a' } }, title ),
			el( 'p', { style: { margin: '0 0 20px', color: '#64748b', fontSize: '14px' } }, desc ),
			onAdd && el(
				Button,
				{
					variant: 'primary',
					icon: 'plus',
					onClick: onAdd,
				},
				__( 'Add Your First Plan', 'whmcs-connector' )
			)
		);
	}

	/**
	 * Component: SkeletonLoader
	 * Renders lightweight shimmer/skeleton cards while data is fetching.
	 */
	function SkeletonLoader( props ) {
		var count = props.count || 3;
		var items = [];
		for ( var i = 0; i < count; i++ ) {
			items.push(
				el(
					'div',
					{
						key: i,
						className: 'whmcs-skeleton-card',
						style: {
							background: '#ffffff',
							border: '1px solid #e2e8f0',
							borderRadius: '20px',
							padding: '32px',
							display: 'flex',
							flexDirection: 'column',
							gap: '16px',
						},
					},
					el( 'div', { style: { height: '24px', width: '60%', background: '#e2e8f0', borderRadius: '4px' } } ),
					el( 'div', { style: { height: '14px', width: '90%', background: '#f1f5f9', borderRadius: '4px' } } ),
					el( 'div', { style: { height: '40px', width: '45%', background: '#e2e8f0', borderRadius: '4px', margin: '12px 0' } } ),
					el( 'div', { style: { height: '36px', width: '100%', background: '#f1f5f9', borderRadius: '8px' } } )
				)
			);
		}

		return el(
			'div',
			{
				style: {
					display: 'grid',
					gridTemplateColumns: 'repeat(' + count + ', minmax(0, 1fr))',
					gap: '24px',
					padding: '20px 0',
				},
			},
			items
		);
	}

	// Expose globally on window.whmcsConnectorCommon
	window.whmcsConnectorCommon = {
		useWhmcsProducts: useWhmcsProducts,
		ProductPicker: ProductPicker,
		FeaturesEditor: FeaturesEditor,
		PlanRepeater: PlanRepeater,
		CycleIntersectionNotice: CycleIntersectionNotice,
		getCycleIntersection: getCycleIntersection,
		EmptyState: EmptyState,
		SkeletonLoader: SkeletonLoader,
	};
} )();
