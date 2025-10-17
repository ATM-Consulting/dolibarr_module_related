/* jshint -W098: suppress the `ATM_MODULE_RELATED is declared but never used` warning */
/* jshint -W117: suppress the `'$' is not defined` warning */
/* jshint -W116: allow if (……) return; (without brackets) */
(function () {
	"use strict";
	window.ATM_MODULE_RELATED = {
		/**
		 *
		 * @param {{relatedBaseURL: string }} dolibarrContext  Contexte passé depuis PHP.
		 */
		main(dolibarrContext) {
			window.addEventListener('DOMContentLoaded', () => {
				this.moveRelatedBlocksAfterTabsAction();
				this.cleanupDuplicateBlocks();

				const ajaxURL = `${dolibarrContext.relatedBaseURL}/script/interface.php`;
				this.initializeAutocomplete(ajaxURL);
			});
		},

		/**
		 * Moves all .blockrelated_content elements to appear after their parent .tabsAction
		 */
		moveRelatedBlocksAfterTabsAction() {
			document.querySelectorAll('.blockrelated_content').forEach(element => {
				const tabsAction = element.closest('div.tabsAction');
				if (tabsAction) {
					tabsAction.insertAdjacentElement('afterend', element);
				}
			});
		},

		/**
		 * Initializes the jQuery UI autocomplete for adding related objects
		 */
		initializeAutocomplete(ajaxURL) {
			const $input = $('#add_related_object');
			if (!$input.length) return;

			$input.autocomplete({
				source: (request, response) => this.fetchAutocompleteData(request, response, ajaxURL),
				minLength: 1,
				select: (event, ui) => this.handleAutocompleteSelect(ui),
				open: function() {
					this.classList.remove('ui-corner-all');
					this.classList.add('ui-corner-top');
				},
				close: function() {
					this.classList.remove('ui-corner-top');
					this.classList.add('ui-corner-all');
				}
			});

			// Custom renderer for categorized items
			$input.autocomplete().data("uiAutocomplete")._renderItem = this.renderAutocompleteItem;
		},

		/**
		 * Fetches autocomplete suggestions from the server
		 */
		fetchAutocompleteData(request, response, ajaxURL) {
			$.ajax({
				url: ajaxURL,
				dataType: "json",
				data: {
					key: request.term,
					get: 'search'
				},
				success: (data) => {
					const items = this.transformAutocompleteData(data);
					response(items);
				}
			});
		},

		/**
		 * Transforms server response into autocomplete items with category headers
		 */
		transformAutocompleteData(data) {
			const items = [];

			Object.entries(data).forEach(([category, categoryData]) => {
				// Add category header
				items.push({
					value: category,
					label: category,
					object: 'title'
				});

				// Add category items
				Object.entries(categoryData).forEach(([id, label]) => {
					items.push({
						value: id,
						label: '  ' + label,
						object: category
					});
				});
			});

			return items;
		},

		/**
		 * Handles selection of an autocomplete item
		 */
		handleAutocompleteSelect(ui) {
			// Prevent selection of category headers
			if (ui.item.object === 'title') {
				return false;
			}

			// Populate hidden fields
			document.getElementById('id_related_object').value = ui.item.value;
			document.getElementById('add_related_object').value = ui.item.label.trim();
			document.getElementById('type_related_object').value = ui.item.object;

			// Show the add button
			const addButton = document.getElementById('bt_add_related_object');
			if (addButton) {
				addButton.style.display = 'inline';
			}

			return false;
		},

		/**
		 * Custom renderer for autocomplete items (bold category headers)
		 */
		renderAutocompleteItem(ul, item) {
			const li = document.createElement('li');
			li.dataset.value = item.value;
			li.textContent = item.label;

			if (item.object === "title") {
				li.style.fontWeight = "bold";
			}

			ul[0].appendChild(li);
			return $(li);
		},

		/**
		 * Removes duplicate .blockrelated_content blocks within .tabsAction
		 */
		cleanupDuplicateBlocks() {
			const tabsAction = document.querySelector('div.tabsAction');
			if (!tabsAction) return;

			const blockrelated = tabsAction.querySelector('.blockrelated_content');
			if (!blockrelated) return;

			const allBlockrelated = document.querySelectorAll('.blockrelated_content');
			if (allBlockrelated.length > 1) {
				blockrelated.remove();
			} else {
				tabsAction.appendChild(blockrelated);
			}
		}
	};
}());
