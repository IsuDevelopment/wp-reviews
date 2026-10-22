/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	Button,
	CheckboxControl,
	PanelBody,
	Placeholder,
	RangeControl,
	ToggleControl,
	ToggleGroupControl as StableToggleGroupControl,
	ToggleGroupControlOption as StableToggleGroupControlOption,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- WP 6.9 fallback; stable names ship with WP 7.0.
	__experimentalToggleGroupControl as ExperimentalToggleGroupControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- See above.
	__experimentalToggleGroupControlOption as ExperimentalToggleGroupControlOption,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

const ToggleGroupControl =
	StableToggleGroupControl || ExperimentalToggleGroupControl;
const ToggleGroupControlOption =
	StableToggleGroupControlOption || ExperimentalToggleGroupControlOption;

const ADMIN_SOURCES_URL = 'admin.php?page=isudev-reviews-sources';

function useRestList( path ) {
	const [ items, setItems ] = useState( null );

	useEffect( () => {
		let active = true;
		apiFetch( { path } )
			.then( ( result ) => active && setItems( result ) )
			.catch( () => active && setItems( [] ) );
		return () => {
			active = false;
		};
	}, [ path ] );

	return items;
}

function TemplatePicker( { templates, value, onChange } ) {
	if ( ! templates ) {
		return null;
	}

	const options = [
		{
			slug: '',
			title: __( 'Site default', 'isudev-reviews' ),
			description: __( 'Follows Reviews › Templates.', 'isudev-reviews' ),
		},
		...templates,
	];

	return (
		<div className="isurev-template-picker">
			{ options.map( ( template ) => (
				<Button
					key={ template.slug || 'default' }
					className="isurev-template-picker__option"
					variant={
						value === template.slug ? 'primary' : 'secondary'
					}
					disabled={ template.locked }
					aria-pressed={ value === template.slug }
					onClick={ () => onChange( template.slug ) }
					__next40pxDefaultSize
				>
					<span className="isurev-template-picker__title">
						{ template.title }
						{ template.tier === 'pro' && (
							<span className="isurev-template-picker__badge">
								{ __( 'Pro', 'isudev-reviews' ) }
							</span>
						) }
					</span>
					{ template.description && (
						<span className="isurev-template-picker__desc">
							{ template.description }
						</span>
					) }
				</Button>
			) ) }
		</div>
	);
}

export default function Edit( { attributes, setAttributes } ) {
	const {
		sourceIds,
		template,
		layout,
		columns,
		limit,
		minRating,
		textLines,
		showSummary,
		withText,
	} = attributes;
	const blockProps = useBlockProps();
	const sources = useRestList( '/isudev-reviews/v1/sources' );
	const templates = useRestList( '/isudev-reviews/v1/templates' );

	const toggleSource = ( id, checked ) =>
		setAttributes( {
			sourceIds: checked
				? [ ...sourceIds, id ]
				: sourceIds.filter( ( sourceId ) => sourceId !== id ),
		} );

	const inspector = (
		<InspectorControls>
			<PanelBody title={ __( 'Sources', 'isudev-reviews' ) }>
				{ sources?.length === 0 && (
					<p>
						<a href={ ADMIN_SOURCES_URL }>
							{ __(
								'Add a review source first.',
								'isudev-reviews'
							) }
						</a>
					</p>
				) }
				{ sources?.map( ( source ) => (
					<CheckboxControl
						key={ source.id }
						label={ `${ source.name } (${ source.platform })` }
						help={ sprintf(
							/* translators: %d: number of stored reviews. */
							__( '%d stored reviews', 'isudev-reviews' ),
							source.stored
						) }
						checked={ sourceIds.includes( source.id ) }
						onChange={ ( checked ) =>
							toggleSource( source.id, checked )
						}
						__nextHasNoMarginBottom
					/>
				) ) }
				{ sources?.length > 0 && (
					<p className="components-base-control__help">
						{ __(
							'None selected shows all sources.',
							'isudev-reviews'
						) }
					</p>
				) }
			</PanelBody>
			<PanelBody title={ __( 'Template', 'isudev-reviews' ) }>
				<TemplatePicker
					templates={ templates }
					value={ template }
					onChange={ ( slug ) => setAttributes( { template: slug } ) }
				/>
			</PanelBody>
			<PanelBody title={ __( 'Layout', 'isudev-reviews' ) }>
				<ToggleGroupControl
					label={ __( 'Layout', 'isudev-reviews' ) }
					value={ layout }
					onChange={ ( value ) => setAttributes( { layout: value } ) }
					isBlock
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				>
					<ToggleGroupControlOption
						value="grid"
						label={ __( 'Grid', 'isudev-reviews' ) }
					/>
					<ToggleGroupControlOption
						value="slider"
						label={ __( 'Carousel', 'isudev-reviews' ) }
					/>
				</ToggleGroupControl>
				<RangeControl
					label={ __( 'Columns', 'isudev-reviews' ) }
					value={ columns }
					onChange={ ( value ) =>
						setAttributes( { columns: value } )
					}
					min={ 1 }
					max={ 6 }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
				<ToggleControl
					label={ __( 'Show rating summary', 'isudev-reviews' ) }
					checked={ showSummary }
					onChange={ ( value ) =>
						setAttributes( { showSummary: value } )
					}
					__nextHasNoMarginBottom
				/>
			</PanelBody>
			<PanelBody title={ __( 'Filters', 'isudev-reviews' ) }>
				<RangeControl
					label={ __( 'Number of reviews', 'isudev-reviews' ) }
					value={ limit }
					onChange={ ( value ) => setAttributes( { limit: value } ) }
					min={ 1 }
					max={ 100 }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
				<RangeControl
					label={ __( 'Minimum rating', 'isudev-reviews' ) }
					value={ minRating }
					onChange={ ( value ) =>
						setAttributes( { minRating: value } )
					}
					min={ 0 }
					max={ 5 }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
				<RangeControl
					label={ __(
						'Visible lines before "Read more"',
						'isudev-reviews'
					) }
					value={ textLines }
					onChange={ ( value ) =>
						setAttributes( { textLines: value } )
					}
					min={ 2 }
					max={ 20 }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
				<ToggleControl
					label={ __( 'Only reviews with text', 'isudev-reviews' ) }
					checked={ withText }
					onChange={ ( value ) =>
						setAttributes( { withText: value } )
					}
					__nextHasNoMarginBottom
				/>
			</PanelBody>
		</InspectorControls>
	);

	return (
		<div { ...blockProps }>
			{ inspector }
			<ServerSideRender
				block="isudev-reviews/reviews"
				attributes={ attributes }
				skipBlockSupportAttributes
				EmptyResponsePlaceholder={ () => (
					<Placeholder
						icon="star-filled"
						label={ __( 'Reviews', 'isudev-reviews' ) }
						instructions={ __(
							'No reviews match these settings yet. Add a source and sync it under Reviews › Sources.',
							'isudev-reviews'
						) }
					/>
				) }
			/>
		</div>
	);
}
