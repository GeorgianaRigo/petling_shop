/**
 * External dependencies
 */
import classnames from 'classnames';

/** @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-components/ */
import {
	Disabled,
	PanelBody,
	SelectControl,
	ToggleControl,
} from '@wordpress/components';

/** @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-server-side-render/ */
import ServerSideRender from '@wordpress/server-side-render';

/** @see https://developer.wordpress.org/block-editor/packages/packages-i18n/ */
import { __ } from '@wordpress/i18n';

/** @see https://developer.wordpress.org/block-editor/packages/packages-block-editor/ */
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';

/** @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-data/ */
import { useSelect } from '@wordpress/data';

/** @see https://www.npmjs.com/package/@wordpress/scripts#using-css */
import './editor.scss';

/**
 * @param  props
 * @see https://developer.wordpress.org/block-editor/developers/block-api/block-edit-save/#edit
 *
 * @return {WPElement} Element to render.
 */
export default function Edit(props) {
	const classnamesArg = {};
	const { deviceType } = useSelect((select) => {
        let deviceType = '';
        const coreEditor = select('core/editor');
        const coreEditPost = select('core/edit-post');

        if (typeof coreEditor === 'object' && typeof coreEditor.getDeviceType === 'function') {
            deviceType = coreEditor.getDeviceType();
        } else if (typeof editPost === 'object' && editPost.__experimentalGetPreviewDeviceType === 'function') {
            deviceType = editPost.__experimentalGetPreviewDeviceType();
        }

        return {
            deviceType,
        };
	}, []);

	if (typeof deviceType === 'string') {
		classnamesArg[
			`wp-block-fibosearch-search__device-preview-${deviceType.toLowerCase()}`
		] = true;
	}
	const blockProps = useBlockProps({
		className: classnames(classnamesArg),
	});

	const { attributes } = props;
	const {
		attributes: {
			darkenedBackground,
			mobileOverlay,
			inheritPluginSettings,
			layout,
		},
		name,
		setAttributes,
	} = props;

	return (
		<div {...blockProps}>
			<InspectorControls key="inspector">
				<PanelBody
					title={__('Settings', 'ajax-search-for-woocommerce')}
					initialOpen={false}
				>
					<ToggleControl
						label={__(
							'Inherit global plugin settings',
							'ajax-search-for-woocommerce'
						)}
						checked={inheritPluginSettings}
						onChange={() =>
							setAttributes({
								inheritPluginSettings: !inheritPluginSettings,
							})
						}
                        __nextHasNoMarginBottom
					/>
					{inheritPluginSettings ? null : (
						<SelectControl
							label={__('Layout', 'ajax-search-for-woocommerce')}
							value={layout}
							options={[
								{
									label: __(
										'Search bar',
										'ajax-search-for-woocommerce'
									),
									value: 'classic',
								},
								{
									label: __(
										'Search icon',
										'ajax-search-for-woocommerce'
									),
									value: 'icon',
								},
								{
									label: __(
										'Icon on mobile, search bar on desktop',
										'ajax-search-for-woocommerce'
									),
									value: 'icon-flexible',
								},
								{
									label: __(
										'Icon on desktop, search bar on mobile',
										'ajax-search-for-woocommerce'
									),
									value: 'icon-flexible-inv',
								},
							]}
							onChange={(newLayout) => {
								setAttributes({
									layout: newLayout,
								});
								if (
									newLayout === 'icon' ||
									newLayout === 'icon-flexible' ||
									newLayout === 'icon-flexible-inv'
								) {
									setAttributes({
										mobileOverlay: true,
									});
								}
							}}
                            __nextHasNoMarginBottom
						/>
					)}
					{inheritPluginSettings ? null : (
						<ToggleControl
							label={__(
								'Darkened background',
								'ajax-search-for-woocommerce'
							)}
							checked={darkenedBackground}
							onChange={() =>
								setAttributes({
									darkenedBackground: !darkenedBackground,
								})
							}
                            __nextHasNoMarginBottom
						/>
					)}
					{inheritPluginSettings ? null : (
						<ToggleControl
							label={__(
								'Overlay on mobile',
								'ajax-search-for-woocommerce'
							)}
							checked={mobileOverlay}
							onChange={() =>
								setAttributes({
									mobileOverlay: !mobileOverlay,
								})
							}
							help={
								mobileOverlay
									? __(
											'The search will open in overlay on mobile',
											'ajax-search-for-woocommerce'
									  )
									: ''
							}
                            __nextHasNoMarginBottom
						/>
					)}
				</PanelBody>
			</InspectorControls>
			<Disabled>
				<ServerSideRender block={name} attributes={attributes} />
			</Disabled>
		</div>
	);
}
