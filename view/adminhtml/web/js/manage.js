/**
 * Copyright (C) 2026 Benjamin Rosenberger <bensch.rosenberger@gmail.com>
 *
 * @copyright 2026 Benjamin Rosenberger
 * @author bensch.rosenberger@gmail.com
 * @license MIT
 * @link https://brocode.at
 */
define([
    'jquery',
    'mage/translate',
    'Magento_Ui/js/modal/alert',
    'Magento_Ui/js/modal/confirm',
    'jquery/ui-modules/widgets/sortable'
], function ($, $t, alert, confirm) {
    'use strict';

    /**
     * System > Tools > Quick Links. Drag & drop and the arrow buttons both reorder the
     * list in the DOM first and then persist the whole order in one request; a failed
     * request puts the previous order back.
     *
     * Every saved change is announced as `brocodeQuickLinksChanged` on document with the
     * full list, so the header chips on this page follow without a reload; chips dragged
     * in the header come back as `brocodeQuickLinksReordered`.
     */
    $.widget('brocode.quickLinksManage', {
        options: {
            saveUrl: '',
            deleteUrl: '',
            reorderUrl: ''
        },

        _create: function () {
            this.list = this.element.find('[data-role=quicklinks-list]');
            this.list.sortable({
                items: '> [data-link-id]',
                handle: '[data-role=drag-handle]',
                axis: 'y',
                helper: this._dragHelper,
                start: function () {
                    this.orderBeforeDrag = this._ids();
                }.bind(this),
                update: function () {
                    this._persistOrder(this.orderBeforeDrag);
                }.bind(this)
            });

            this.element.on('click', '[data-action=up]', this._move.bind(this, -1));
            this.element.on('click', '[data-action=down]', this._move.bind(this, 1));
            this.element.on('click', '[data-action=edit]', this._startEdit.bind(this));
            this.element.on('click', '[data-action=cancel-edit]', this._cancelEdit.bind(this));
            this.element.on('click', '[data-action=save-edit]', this._saveEdit.bind(this));
            this.element.on('keydown', '[data-role=edit] input', this._editKeys.bind(this));
            this.element.on('change', '[data-action=pin]', this._pin.bind(this));
            this.element.on('click', '[data-action=delete]', this._delete.bind(this));
            this.element.on('submit', '[data-role=add-form]', this._add.bind(this));
            $(document).on('brocodeQuickLinksReordered', function (event, ids) {
                ids.forEach(function (id) {
                    this.list.append(this.list.children('[data-link-id="' + id + '"]'));
                }, this);
                this._refresh();
            }.bind(this));
            this._refresh();
        },

        /**
         * A dragged table row loses its column widths; the copy keeps them.
         */
        _dragHelper: function (event, row) {
            var helper = row.clone().addClass('_dragged');

            helper.children().each(function (index) {
                $(this).width(row.children().eq(index).width());
            });

            return helper;
        },

        _row: function (event) {
            return $(event.currentTarget).closest('[data-link-id]');
        },

        _ids: function () {
            return this.list.children('[data-link-id]').map(function () {
                return $(this).attr('data-link-id');
            }).get();
        },

        _move: function (direction, event) {
            var row = this._row(event),
                before = this._ids(),
                sibling = direction < 0 ? row.prev('[data-link-id]') : row.next('[data-link-id]');

            if (!sibling.length) {
                return;
            }

            if (direction < 0) {
                row.insertBefore(sibling);
            } else {
                row.insertAfter(sibling);
            }

            $(event.currentTarget).trigger('focus');
            this._persistOrder(before);
        },

        _persistOrder: function (before) {
            this._refresh();
            this._post(this.options.reorderUrl, {ids: this._ids()})
                .done(this._announce.bind(this))
                .fail(function () {
                    before.forEach(function (id) {
                        this.list.append(this.list.children('[data-link-id="' + id + '"]'));
                    }, this);
                    this._refresh();
                }.bind(this));
        },

        _startEdit: function (event) {
            var row = this._row(event);

            row.addClass('_editing');
            row.find('[data-role=view]').prop('hidden', true);
            row.find('[data-role=edit]').prop('hidden', false).find('input').first().trigger('focus');
        },

        _endEdit: function (row) {
            row.removeClass('_editing');
            row.find('[data-role=edit]').prop('hidden', true);
            row.find('[data-role=view]').prop('hidden', false);
            row.find('[data-action=edit]').trigger('focus');
        },

        _cancelEdit: function (event) {
            var row = this._row(event),
                link = row.find('[data-role=label-link]');

            row.find('[data-role=edit] input[name=label]').val(link.text());
            row.find('[data-role=edit] input[name=url]').val(row.find('[data-role=url-value]').text());
            this._endEdit(row);
        },

        _editKeys: function (event) {
            if (event.key === 'Escape') {
                this._cancelEdit(event);
            } else if (event.key === 'Enter') {
                event.preventDefault();
                this._saveEdit(event);
            }
        },

        _saveEdit: function (event) {
            var row = this._row(event),
                data = {id: row.attr('data-link-id'), label: row.find('[data-role=edit] input[name=label]').val()},
                urlInput = row.find('[data-role=edit] input[name=url]');

            if (urlInput.length) {
                data.url = urlInput.val();
            }

            this._post(this.options.saveUrl, data).done(function (response) {
                row.find('[data-role=label-link]').text(response.link.label).attr('href', response.link.href);
                row.find('[data-role=edit] input[name=label]').val(response.link.label);

                // Only an external link's URL can change; an internal one is shown decoded
                if (urlInput.length) {
                    row.find('[data-role=url-value]').text(response.link.url);
                    row.find('[data-role=url-text]').attr('title', response.link.url);
                    urlInput.val(response.link.url);
                }
                this._endEdit(row);
                this._announce();
            }.bind(this));
        },

        _pin: function (event) {
            var checkbox = $(event.currentTarget),
                pinned = checkbox.prop('checked');

            this._post(this.options.saveUrl, {id: this._row(event).attr('data-link-id'), pinned: pinned ? 1 : 0})
                .done(this._announce.bind(this))
                .fail(function () {
                    checkbox.prop('checked', !pinned);
                });
        },

        _delete: function (event) {
            var row = this._row(event),
                self = this;

            confirm({
                content: $t('Delete the quick link "%1"?').replace('%1', row.find('[data-role=label-link]').text()),
                actions: {
                    confirm: function () {
                        self._post(self.options.deleteUrl, {id: row.attr('data-link-id')}).done(function () {
                            row.remove();
                            self._refresh();
                            self._announce();
                        });
                    }
                }
            });
        },

        _add: function (event) {
            var form = $(event.currentTarget);

            event.preventDefault();
            this._post(this.options.saveUrl, {
                label: form.find('input[name=label]').val(),
                url: form.find('input[name=url]').val()
            }).done(function () {
                window.location.reload();
            });
        },

        /**
         * The list as the header widget renders it: all links, in order.
         */
        _announce: function () {
            var links = this.list.children('[data-link-id]').map(function () {
                var row = $(this),
                    anchor = row.find('[data-role=label-link]');

                return {
                    id: row.attr('data-link-id'),
                    label: anchor.text(),
                    href: anchor.attr('href'),
                    'is_external': row.attr('data-external') === '1',
                    'is_pinned': row.find('[data-action=pin]').prop('checked')
                };
            }).get();

            $(document).trigger('brocodeQuickLinksChanged', [links]);
        },

        /**
         * First row cannot move up, last row cannot move down.
         */
        _refresh: function () {
            var rows = this.list.children('[data-link-id]');

            rows.find('[data-action=up], [data-action=down]').prop('disabled', false);
            rows.first().find('[data-action=up]').prop('disabled', true);
            rows.last().find('[data-action=down]').prop('disabled', true);
            this.element.find('[data-role=empty]').prop('hidden', rows.length > 0);
        },

        _post: function (url, data) {
            return $.ajax({
                url: url,
                type: 'POST',
                dataType: 'json',
                showLoader: true,
                data: $.extend({'form_key': window.FORM_KEY}, data)
            }).then(function (response) {
                return response.success ? response : $.Deferred().reject(response.message).promise();
            }).fail(function (message) {
                alert({content: typeof message === 'string' ? message : $t('The request failed. Please try again.')});
            });
        }
    });

    return $.brocode.quickLinksManage;
});
