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
     * Bookmarks restore the user's saved grid view after the filters exist; applying
     * before that would be overwritten. Same wait as core's url-filter-applier.
     */
    function whenRestored(ns, callback, tries) {
        var bookmarks = registry.get('componentType = bookmark, ns = ' + ns);

        if (bookmarks && !_.size(bookmarks.getViewData(bookmarks.defaultIndex)) && tries > 0) {
            setTimeout(function () {
                whenRestored(ns, callback, tries - 1);
            }, BOOKMARK_WAIT_MS);

            return;
        }

        callback();
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
         * Whether a saved state (or none, see LinkUrl::isUnfilteredGridState()) shows the
         * grid without filters and keyword.
         */
        isUnfiltered: function (serialized) {
            var state = serialized ? JSON.parse(serialized) : {};

            return _.isEmpty(state.f) && !state.s;
        },

        /**
         * A short, human-readable version for a link label: "pending, Veronica"; empty
         * for an unfiltered grid.
         */
        summary: function (serialized) {
            var state = JSON.parse(serialized),
                parts = _.map(state.f || {}, function (value) {
                    if (Array.isArray(value)) {
                        return value.join('/');
                    }

                    if (value && typeof value === 'object') {
                        return _.compact([value.from, value.to]).join('–');
                    }

                    return String(value);
                });

            if (state.s) {
                parts.push(state.s);
            }

            return parts.join(', ');
        },

        /**
         * Replaces the grid's filters and keyword with a saved state.
         *
         * With bookmarks the state is written into the current view: filters and search
         * import it through their statefull links, also a link that is only set up after
         * this runs (the search box's often is, and would otherwise pull the old keyword
         * back in). Grids without bookmarks get the values set directly; the keyword goes
         * first there, since changing it resets the filters, and an empty keyword needs
         * clear(): core's apply('') falls back to the text still in the search box.
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
                whenRestored(state.ns, function () {
                    var bookmarks = registry.get('componentType = bookmark, ns = ' + state.ns),
                        applied = $.extend({placeholder: true}, state.f || {}),
                        search = searchOf(state.ns);

                    if (bookmarks) {
                        bookmarks.set('current.search.value', state.s || '');
                        bookmarks.set('current.filters.applied', applied);

                        return;
                    }

                    if (search && state.s && search.value !== state.s) {
                        search.apply(state.s);
                    } else if (search && !state.s && search.value) {
                        search.clear();
                    }

                    filters.set('applied', applied);
                }, BOOKMARK_WAIT_TRIES);
            });
        }
    };
});
