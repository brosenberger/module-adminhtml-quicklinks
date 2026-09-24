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
    'underscore',
    'uiRegistry',
    'mage/translate',
    'Magento_Ui/js/modal/alert',
    'BroCode_AdminhtmlQuickLinks/js/grid-state',
    'jquery/ui-modules/widgets/sortable'
], function ($, _, registry, $t, alert, gridState) {
    'use strict';

    /**
     * Header star: adds the current page as a pinned quick link, or removes it again.
     * The pinned chips live in a separate header row, so they are looked up by selector;
     * they can be dragged into a new order.
     *
     * On a grid page the link carries the grid's filters and keyword (see grid-state.js),
     * and the star is filled only while the grid shows exactly the state of one of the
     * links to this page.
     *
     * When Magento's page actions bar turns sticky on scroll (mage/backend/floating-header
     * adds `_fixed`), a copy of the chips is placed into it, between title and buttons.
     * The copy is made lazily at that moment because floating-header rebuilds the bar's
     * markup on init, and is only shown while the bar is fixed (see quicklinks.css).
     *
     * Pages without that bar (invoice grids, reports, ...) get a bar of their own, built
     * from the same core markup so core's CSS styles it identically, and shown only
     * while the header chip row is scrolled out of view.
     *
     * The manage page announces its changes with a `brocodeQuickLinksChanged` event on
     * document; the chips are rebuilt from it without a reload.
     */
    $.widget('brocode.quickLinksToggle', {
        options: {
            saveUrl: '',
            deleteUrl: '',
            reorderUrl: '',
            pageLinks: [],
            order: [],
            stateParam: 'ql_state'
        },

        _create: function () {
            this.star = this.element.find('[data-role=quicklinks-star]');
            this.bar = $('[data-role=quicklinks-bar]');
            this.menu = this.element.find('[data-role=quicklinks-overflow]');
            this.pageLinks = this.options.pageLinks.slice();
            this.order = this.options.order.map(String);
            this.star.on('click', this._toggle.bind(this));
            this._initSortable();
            this._initGrid();
            $(document).on('brocodeQuickLinksChanged', function (event, links) {
                this._rebuild(links);
            }.bind(this));
            // Deferred a frame: floating-header sets `_fixed` in its own scroll handler,
            // which may run after this one.
            $(window).on('scroll resize', function () {
                window.requestAnimationFrame(this._syncSticky.bind(this));
            }.bind(this));
            window.requestAnimationFrame(this._syncSticky.bind(this));
        },

        /**
         * Applies the grid state a link arrived with, then drops it from the address bar,
         * so a reload keeps whatever the user changes afterwards.
         */
        _initGrid: function () {
            var url = new URL(window.location.href),
                state = url.searchParams.get(this.options.stateParam);

            if (state) {
                gridState.apply(state);
                url.searchParams.delete(this.options.stateParam);
                window.history.replaceState(window.history.state, '', url.toString());
            }

            gridState.onGrid(function (ns, filters) {
                var search = registry.get('index = fulltext, ns = ' + ns),
                    sync = _.debounce(this._syncStar.bind(this), 50);

                this.ns = ns;
                filters.on('applied', sync);

                if (search) {
                    search.on('value', sync);
                }

                sync();
            }.bind(this));
        },

        _gridState: function () {
            return this.ns ? gridState.serialize(this.ns) : null;
        },

        /**
         * @returns {Object|undefined} the link to this page showing what is on screen. On a
         *     grid page only a link with the grid's exact state counts: one without any
         *     state (saved before states were recorded, or added by URL) opens the grid as
         *     last used, so it does not stand for the unfiltered view either.
         */
        _currentLink: function () {
            var state = this._gridState();

            return _.find(this.pageLinks, function (link) {
                return link.state === state;
            });
        },

        _syncStar: function () {
            this.star.attr('aria-pressed', this._currentLink() ? 'true' : 'false');
        },

        _initSortable: function () {
            this.bar.sortable({
                items: '> [data-link-id]',
                // A clone, not the chip itself: sortable re-reads the first item's `float`
                // on every move (see quicklinks.css), and a dragged original is absolutely
                // positioned, which computes its float to none.
                helper: 'clone',
                distance: 5,
                tolerance: 'pointer',
                start: function () {
                    this.orderBeforeDrag = this.order.slice();
                }.bind(this),
                update: this._persistChipOrder.bind(this)
            });
        },

        /**
         * The chips hold only the pinned links; the unpinned ones keep their positions in
         * the full order, and the pinned positions are refilled in the dragged order.
         */
        _persistChipOrder: function () {
            var before = this.orderBeforeDrag,
                pinned = this._barIds(),
                pinnedSet = _.object(pinned, pinned),
                next = 0;

            this.order = before.map(function (id) {
                return _.has(pinnedSet, id) ? pinned[next++] : id;
            });
            this._chipsChanged();
            this._post(this.options.reorderUrl, {ids: this.order}).done(function () {
                $(document).trigger('brocodeQuickLinksReordered', [this.order.slice()]);
            }.bind(this)).fail(function () {
                this.order = before;
                before.forEach(function (id) {
                    this.bar.append(this.bar.children('[data-link-id="' + id + '"]'));
                }, this);
                this._chipsChanged();
            }.bind(this));
        },

        _barIds: function () {
            return this.bar.children('[data-link-id]').map(function () {
                return $(this).attr('data-link-id');
            }).get();
        },

        _syncSticky: function () {
            var coreInner = $('.page-actions-inner').filter(function () {
                return !$(this).closest('.brocode-quicklinks-fixedbar').length;
            });

            if (coreInner.length) {
                this._addCopy(coreInner.filter(function () {
                    return $(this).parent().hasClass('_fixed');
                }));

                return;
            }

            this._syncOwnBar();
        },

        _addCopy: function (inner) {
            var copy;

            if (inner.length && !inner.children('.brocode-quicklinks-sticky').length) {
                copy = this.bar.clone().removeAttr('data-role').addClass('brocode-quicklinks-sticky');
                copy.removeClass('ui-sortable');
                inner.append(copy);
            }
        },

        _syncOwnBar: function () {
            var show = this.bar.children().length > 0 && this.bar[0].getBoundingClientRect().bottom < 0;

            if (show && !this.ownBar) {
                // `_hidden` on the wrapper is core's own "the bar is fixed" state: it drops the
                // wrapper's background and padding, leaving only the fixed .page-actions.
                this.ownBar = $(
                    '<div class="page-main-actions _hidden brocode-quicklinks-fixedbar" hidden>' +
                    '<div class="page-actions _fixed"><div class="page-actions-inner"></div></div></div>'
                );
                this.ownBar.find('.page-actions-inner')
                    .attr('data-title', $('.page-title-wrapper .page-title').first().text().trim());
                $('body').append(this.ownBar);
                this._addCopy(this.ownBar.find('.page-actions-inner'));
            }

            if (this.ownBar) {
                this.ownBar.prop('hidden', !show);
            }
        },

        _chipsChanged: function () {
            $('.brocode-quicklinks-sticky').remove();

            if (this.ownBar) {
                this.ownBar.remove();
                this.ownBar = null;
            }

            this.bar.sortable('refresh');
            this._syncSticky();
        },

        /**
         * Rebuilds chips and dropdown from the manage page's list (in order, all links).
         */
        _rebuild: function (links) {
            var ids = _.pluck(links, 'id').map(String);

            this.order = ids;
            this.pageLinks = this.pageLinks.filter(function (link) {
                return ids.indexOf(String(link.id)) !== -1;
            });
            this.bar.children('[data-link-id]').remove();
            this.menu.children('[data-link-id]').remove();
            links.forEach(function (link) {
                if (link.is_pinned) {
                    this.bar.append(this._chip(link));
                } else {
                    this.menu.children('.brocode-quicklinks-manage').before(this._menuItem(link));
                }
            }, this);
            this._syncStar();
            this._chipsChanged();
        },

        _toggle: function () {
            var current = this._currentLink(),
                state = this._gridState();

            if (current) {
                this._post(this.options.deleteUrl, {id: current.id}).done(function () {
                    $('[data-role=quicklinks-bar], [data-role=quicklinks-overflow]')
                        .children('[data-link-id="' + current.id + '"]').remove();
                    this.pageLinks = _.without(this.pageLinks, current);
                    this.order = _.without(this.order, String(current.id));
                    this._syncStar();
                    this._chipsChanged();
                }.bind(this));

                return;
            }

            this._post(this.options.saveUrl, {url: this._pageUrl(state), label: this._pageLabel()})
                .done(function (response) {
                    this.bar.append(this._chip(response.link));
                    this.pageLinks.push({id: response.link.id, state: state});
                    this.order.push(String(response.link.id));
                    this._syncStar();
                    this._chipsChanged();
                }.bind(this));
        },

        _pageUrl: function (state) {
            var url = new URL(window.location.href);

            url.searchParams.delete(this.options.stateParam);

            if (state) {
                url.searchParams.set(this.options.stateParam, state);
            }

            return url.toString();
        },

        /**
         * "Orders / Operations / Sales / Magento Admin" -> "Orders", plus what sets this
         * page apart from others with the same title: "Orders: Pending", "Customers: Second
         * Website", "Configuration: Catalog" (config sections all share one title), and a
         * config scope other than default: "Configuration: Payment Methods (Second Website)".
         */
        _pageLabel: function () {
            var title = document.title.split(' / ')[0].trim() || window.location.pathname,
                path = window.location.pathname,
                detail = this.ns ? gridState.describe(this.ns) : '';

            if (!detail && path.indexOf('/system_config/') !== -1) {
                detail = $('.admin__page-nav-item._active').first().text().trim();

                if (/\/(website|store)\/\d+/.test(path)) {
                    detail += ' (' + $('#store-change-button').first().text().trim() + ')';
                }
            }

            return detail ? title + ': ' + detail : title;
        },

        _chip: function (link) {
            var anchor = $('<a class="brocode-quicklinks-chip"></a>')
                .attr('href', link.href)
                .attr('title', link.label)
                .text(link.label);

            if (link.is_external) {
                anchor.addClass('_external').attr({target: '_blank', rel: 'noopener noreferrer'});
            }

            return $('<li class="brocode-quicklinks-item"></li>').attr('data-link-id', link.id).append(anchor);
        },

        _menuItem: function (link) {
            var anchor = $('<a></a>').attr('href', link.href).text(link.label);

            if (link.is_external) {
                anchor.addClass('_external').attr({target: '_blank', rel: 'noopener noreferrer'});
            }

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
