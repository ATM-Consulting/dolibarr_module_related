const ATM_MODULE_RELATED = {
	main(dolibarrContext) {

		$(document).ready(function () {

			$('.blockrelated_content').each(function () {
				$(this).closest('div.tabsAction').after($(this));
			});

			$('#add_related_object').autocomplete({
				source: function (request, response) {
					$.ajax({
						url: dolibarrContext.ajaxURL, dataType: "json", data: {
							key: request.term, get: 'search'
						}, success: function (data) {
							var c = [];
							$.each(data, function (i, cat) {

								var first = true;
								$.each(cat, function (j, label) {

									if (first) {
										c.push({value: i, label: i, object: 'title'});
										first = false;
									}

									c.push({value: j, label: '  ' + label, object: i});

								});


							});

							response(c);


						}
					});
				}, minLength: 1, select: function (event, ui) {

					if (ui.item.object == 'title') return false; else {
						$('#id_related_object').val(ui.item.value);
						$('#add_related_object').val(ui.item.label.trim());
						$('#type_related_object').val(ui.item.object);

						$('#bt_add_related_object').css('display', 'inline');

						return false;
					}

				}, open: function (event, ui) {
					$(this).removeClass("ui-corner-all").addClass("ui-corner-top");
				}, close: function () {
					$(this).removeClass("ui-corner-top").addClass("ui-corner-all");
				}
			});

			$("#add_related_object").autocomplete().data("uiAutocomplete")._renderItem = function (ul, item) {

				$li = $("<li />")
					.attr("data-value", item.value)
					.append(item.label)
					.appendTo(ul);

				if (item.object == "title") $li.css("font-weight", "bold");

				return $li;
			};


			var blockrelated = $('div.tabsAction .blockrelated_content');
			if (blockrelated.length == 1) {
				if ($('.blockrelated_content').length > 1) {
					blockrelated.remove();
				} else {
					blockrelated.appendTo($('div.tabsAction'));
				}
			}

		});
	}
};
