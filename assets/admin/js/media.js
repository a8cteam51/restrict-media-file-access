/* global jQuery, MutationObserver, _ */
// Media library JS
(function ($) {
	('use strict');

	const BTN_SELECTOR = '.media-button-select';
	const DETAILS_SELECTOR = '.media-sidebar .attachment-details';
	const WAIT_CLASS = 'save-waiting';
	const RESTRICTED_CLASS = 'rmfa-restricted-file';
	const RESTRICTED_TOGGLE_SELECTOR = '.rmfa-restricted-toggle';
	const ATTACHMENT_MEDIA_VIEW_DETAILS = '.attachment-details';

	// Will hold the currently-active observers/elements
	let detailsObserver = null; // watches attribute changes on sidebar
	let containerObserver = null; // watches DOM replacements
	let observedSidebar = null; // node we're currently observing

	/* -----------------------------------------------------------
	 * Helpers
	 * --------------------------------------------------------- */
	function toggleSelectButton(waiting) {
		const $btn = $(BTN_SELECTOR);
		$btn.prop('disabled', waiting).text(
			waiting ? 'Updating file…' : 'Select'
		);
	}

	const onDetailsChange = _.throttle(() => {
		const $details = $(DETAILS_SELECTOR);
		if ($details.length) {
			const isWaiting = $details.hasClass(WAIT_CLASS);
			toggleSelectButton(isWaiting);
		}
	}, 200);

	/** Attach (or re-attach) the attribute observer to the latest sidebar */
	function attachSidebarObserver() {
		const sidebar = document.querySelector('.media-sidebar');
		if (!sidebar || sidebar === observedSidebar) {
			return; // nothing new
		}

		// Replace the old attribute observer
		if (detailsObserver) {
			detailsObserver.disconnect();
		}

		observedSidebar = sidebar;
		detailsObserver = new MutationObserver(onDetailsChange);
		detailsObserver.observe(sidebar, {
			subtree: true,
			attributes: true,
			attributeFilter: ['class'],
		});

		onDetailsChange(); // initialise button state
	}

	/**
	 * Check if the media modal is currently opened
	 */
	function isMediaModalOpen() {
		// Method 1: Check if the modal element exists and is visible
		const $modal = $('.media-modal');
		if ($modal.length && $modal.is(':visible')) {
			return true;
		}

		// Method 2: Check if wp.media.frame exists and is open
		if (
			wp.media &&
			wp.media.frame &&
			wp.media.frame.isOpen &&
			wp.media.frame.isOpen()
		) {
			return true;
		}

		// Method 3: Check if the modal has the 'open' class
		if ($('.media-modal').hasClass('open')) {
			return true;
		}

		// Method 4: Check if the modal is in the DOM and not hidden
		const modalElement = document.querySelector('.media-modal');
		if (
			modalElement &&
			modalElement.style.display !== 'none' &&
			modalElement.style.visibility !== 'hidden'
		) {
			return true;
		}

		return false;
	}

	/* -----------------------------------------------------------
	 * Event Handlers
	 * --------------------------------------------------------- */

	/**
	 * Handle restriction toggle change
	 */
	function handleRestrictionToggle() {
		const $toggle = $(this);
		const $details = $(DETAILS_SELECTOR);

		// Store the current attachment ID
		currentAttachmentId = $toggle.data('attachment-id');

		$details.toggleClass(RESTRICTED_CLASS, $toggle.checked);

		// Add waiting class to show processing state
		$details.addClass(WAIT_CLASS);
	}

	/* -----------------------------------------------------------
	 * Modal lifecycle media library
	 * --------------------------------------------------------- */
	wp.media.view.Modal.prototype.on('open', function () {
		// 1⃣  Attribute observer for the sidebar
		attachSidebarObserver();

		// 2⃣  Container observer: re-run attachSidebarObserver
		const modal = document.querySelector('.media-modal');
		if (modal) {
			containerObserver = new MutationObserver(attachSidebarObserver);
			containerObserver.observe(modal, {
				childList: true,
				subtree: true,
			});
		}

		// Bind restriction toggle event
		$(document).on(
			'change',
			RESTRICTED_TOGGLE_SELECTOR,
			handleRestrictionToggle
		);
	});

	wp.media.view.Modal.prototype.on('close', function () {
		if (detailsObserver) {
			detailsObserver.disconnect();
			detailsObserver = null;
		}
		if (containerObserver) {
			containerObserver.disconnect();
			containerObserver = null;
		}
		observedSidebar = null;

		// Remove event handler
		$(document).off(
			'change',
			RESTRICTED_TOGGLE_SELECTOR,
			handleRestrictionToggle
		);
	});

	/* -----------------------------------------------------------
	 * Attachment render in media library
	 * --------------------------------------------------------- */
	const OriginalRender = wp.media.view.Attachment.prototype.render;

	wp.media.view.Attachment.prototype.render = function () {
		const result = OriginalRender.call(this);

		const isProtected = this.model.get('isProtected');
		const rmfaClasses = this.model.get('rmfaClasses');

		const frameState = wp.media.frame.state();
		let isSelected = false;

		const mediaSrc =
			this.model.get( 'type' ) === 'image'
				? this.model.get( 'url' )
				: this.model.get( 'icon' );


		// If the modal is not in the library, we consider the file selected
		if ('library' !== frameState.attributes.id) {
			isSelected = true;
		} else {
			isSelected = wp.media.frame
				.state()
				.get('selection')
				.has(this.model);
		}

		if ('undefined' === typeof rmfaClasses || rmfaClasses.length <= 0) {
			return result;
		}

		// Add custom classes after rendering
		if (isProtected) {
			this.$el.addClass(rmfaClasses.join(' '));

			if (isSelected) {
				$(ATTACHMENT_MEDIA_VIEW_DETAILS).addClass(
					rmfaClasses.join(' ')
				);
			}
		} else {
			this.$el.removeClass(rmfaClasses.join(' '));

			if (isSelected) {
				$(ATTACHMENT_MEDIA_VIEW_DETAILS).removeClass(
					rmfaClasses.join(' ')
				);
			}
		}

		if (isMediaModalOpen() && isSelected) {
			// Add the file URL to the attachment details
			const $viewMediaModal = $(ATTACHMENT_MEDIA_VIEW_DETAILS);
			$viewMediaModal
				.find('.details-image')
				.attr('src', mediaSrc);

			$viewMediaModal
				.find('.attachment-details-copy-link')
				.attr('value', this.model.get('url'));
		}

		return result;
	};
})(jQuery);
