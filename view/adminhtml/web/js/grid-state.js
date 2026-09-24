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
    'uiRegistry'
], function ($, _, registry) {
    'use strict';

    /**
     * The filters and search keyword of the page's admin grid, as a link can carry them.
     *
     * Grids keep that state in the user's UI bookmarks, not in the URL, so a plain link to
     * a filtered grid opens it unfiltered (or with whatever was used last). A quick link
     * stores the state as canonical JSON (`{"f": filters, "ns": namespace, "s": keyword}`)
     * in its own query parameter and applies it here on arrival. An unfiltered grid is
     * saved as `{"ns": namespace}`, so its link opens the grid unfiltered again.
     *
     * Core's Magento_Ui/js/grid/url-filter-applier does the arrival half for flat values
     * only and merges into the current filters; a saved link has to replace them, ranges
     * (dates, prices) included.
     */
    var BOOKMARK_WAIT_MS = 100,
        BOOKMARK_WAIT_TRIES = 50;

    /**
     * Object keys sorted at every level, so equal states serialise to equal strings.
     */
    function canonical(value) {
        if (Array.isArray(value)) {
            return value.map(canonical);
        }

        if (value && typeof value === 'object') {
            return Object.keys(value).sort().reduce(function (result, key) {
                result[key] = canonical(value[key]);

                return result;
            }, {});
        }

        return value;
    }

    function isEmpty(value) {
        if (value === undefined || value === null || value === '') {
            return true;
        }

        if (typeof value === 'object') {
            return _.every(value, isEmpty);
        }

        return false;
    }

    function filtersOf(ns) {
        return registry.get('componentType = filters, ns = ' + ns);
    }

    function searchOf(ns) {
        return registry.get('index = fulltext, ns = ' + ns);
    }

    /**
     * Waits until the bookmarks hold the user's saved views. Same wait as core's
     * url-filter-applier.
     */
    function whenRestored(bookmarks, callback, tries) {
        if (!_.size(bookmarks.getViewData(bookmarks.defaultIndex)) && tries > 0) {
            setTimeout(function () {
                whenRestored(bookmarks, callback, tries - 1);
            }, BOOKMARK_WAIT_MS);

            return;
        }

        callback();
    }

    /**
     * For a grid without bookmarks. The keyword goes first, since changing it resets the
     * filters, and an empty keyword needs clear(): core's apply('') falls back to the text
     * still in the search box.
     */
    function applyDirectly(filters, search, state, applied) {
        if (search && state.s && search.value !== state.s) {
            search.apply(state.s);
        } else if (search && !state.s && search.value) {
            search.clear();
        }

        filters.set('applied', applied);
    }

    return {
        /**
         * Calls back with the namespace of the page's first grid that has filters.
         */
        onGrid: function (callback) {
            registry.get('componentType = filters', function (filters) {
                callback(filters.ns, filters);
            });
        },

        /**
         * @returns {String|null} canonical JSON, or null when the page has no such grid
         */
        serialize: function (ns) {
            var filters = filtersOf(ns),
                search = searchOf(ns),
                applied = {},
                state = {ns: ns};

            if (!filters) {
                return null;
            }

            _.each(filters.get('applied'), function (value, key) {
                if (key !== 'placeholder' && !isEmpty(value)) {
                    applied[key] = value;
                }
            });

            if (!_.isEmpty(applied)) {
                state.f = applied;
            }

            if (search && search.value) {
                state.s = search.value;
            }

            return JSON.stringify(canonical(state));
        },

        /**
         * The grid's active filters and keyword as the grid itself shows them in its filter
         * chips, for a link label: "Second Store View, 10 - ..., Pending, Veronica".
         * Option labels, not stored values, so a website filter reads "Second Website", not "3".
         */
        describe: function (ns) {
            var filters = filtersOf(ns),
                search = searchOf(ns),
                parts = _.map(filters ? filters.previews : [], function (item) {
                    var preview = item.preview;

                    if (Array.isArray(preview)) {
                        return (preview[0] || '...') + ' - ' + (preview[1] || '...');
                    }

                    return String(preview).trim();
                });

            if (search && search.value) {
                parts.push(search.value);
            }

            return _.compact(parts).join(', ');
        },

        /**
         * Replaces the grid's filters and keyword with a saved state.
         *
         * With bookmarks the state is written into the current view: filters and search
         * import it through their statefull links, also a link that is only set up after
         * this runs (the search box's often is, and would otherwise pull the old keyword
         * back in). The bookmarks are waited for through the filters' own storage
         * provider: they can register after the filters, and restoring the saved view
         * then would overwrite a state written earlier.
         */
        apply: function (serialized) {
            var state;

            try {
                state = JSON.parse(serialized);
            } catch (e) {
                return;
            }

            if (!state || !state.ns) {
                return;
            }

            registry.get('componentType = filters, ns = ' + state.ns, function (filters) {
                var provider = filters.storageConfig && filters.storageConfig.provider,
                    applied = $.extend({placeholder: true}, state.f || {});

                if (!provider || provider === 'localStorage') {
                    applyDirectly(filters, searchOf(state.ns), state, applied);

                    return;
                }

                registry.get(provider, function (bookmarks) {
                    whenRestored(bookmarks, function () {
                        bookmarks.set('current.search.value', state.s || '');
                        bookmarks.set('current.filters.applied', applied);
                    }, BOOKMARK_WAIT_TRIES);
                });
            });
        }
    };
});
