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
    'Magento_Ui/js/modal/alert'
], function ($, $t, alert) {
    'use strict';

    /**
     * Header star: adds the current page as a pinned quick link, or removes it again.
     * The pinned chips live in a separate header row, so they are looked up by selector.
     *
     * When Magento's page actions bar turns sticky on scroll (mage/backend/floating-header
     * adds `_fixed`), a copy of the chips is placed into it, between title and buttons.
     * The copy is made lazily at that moment because floating-header rebuilds the bar's
     * markup on init, and is only shown while the bar is fixed (see quicklinks.css).
     */
    $.widget('brocode.quickLinksToggle', {
        options: {
            saveUrl: '',
            deleteUrl: '',
            currentLinkId: null
        },

        _create: function () {
            this.star = this.element.find('[data-role=quicklinks-star]');
            this.bar = $('[data-role=quicklinks-bar]');
            this.star.on('click', this._toggle.bind(this));
            // Deferred a frame: floating-header sets `_fixed` in its own scroll handler,
            // which may run after this one.
            $(window).on('scroll resize', function () {
                window.requestAnimationFrame(this._syncSticky.bind(this));
            }.bind(this));
        },

        _syncSticky: function () {
            var inner = $('.page-actions._fixed .page-actions-inner');

            if (inner.length && !inner.children('.brocode-quicklinks-sticky').length) {
                inner.append(this.bar.clone().removeAttr('data-role').addClass('brocode-quicklinks-sticky'));
            }
        },

        _chipsChanged: function () {
            $('.brocode-quicklinks-sticky').remove();
            this._syncSticky();
        },

        _toggle: function () {
            var id = this.options.currentLinkId;

            if (id) {
                this._post(this.options.deleteUrl, {id: id}).done(function () {
                    this.element.add(this.bar).find('[data-link-id="' + id + '"]').remove();
                    this._setCurrent(null);
                    this._chipsChanged();
                }.bind(this));

                return;
            }

            this._post(this.options.saveUrl, {url: window.location.href, label: this._pageLabel()})
                .done(function (response) {
                    this.bar.append(this._chip(response.link));
                    this._setCurrent(response.link.id);
                    this._chipsChanged();
                }.bind(this));
        },

        _setCurrent: function (id) {
            this.options.currentLinkId = id;
            this.star.attr('aria-pressed', id ? 'true' : 'false');
        },

        /**
         * "Orders / Operations / Sales / Magento Admin" -> "Orders"
         */
        _pageLabel: function () {
            return document.title.split(' / ')[0].trim() || window.location.pathname;
        },

        _chip: function (link) {
            var anchor = $('<a class="brocode-quicklinks-chip"></a>')
                .attr('href', link.href)
                .attr('title', link.label)
                .text(link.label);

            return $('<li class="brocode-quicklinks-item"></li>').attr('data-link-id', link.id).append(anchor);
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
                alert({content: typeof message === 'string' ? message : $t('The quick link could not be saved.')});
            });
        }
    });

    return $.brocode.quickLinksToggle;
});
