// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Javascript to handle personalschedule validation.
 *
 * @module     mod_personalschedule/validation
 * @package    mod_personalschedule
 * @copyright  2019 Vladislav Kovalev snouwer@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/str', 'core/modal_factory', 'core/notification'], function($, Str, ModalFactory, Notification) {
    var ScheduleElementTypeEnum = Object.freeze({"sleep": "-1", "busy": "0", "free": "1"});
    var HiddenInputSeparator = ";";

    var ReadinessKeyPrefix = "-2";

    var AgeMin = 5;
    var AgeMax = 105;
    var strings = [
    {
        key: 'validation_schedulesetting',
        component: 'personalschedule'
    },
    {
        key: 'validation_readinesssetting',
        component: 'personalschedule'
    },
    {
        key: 'validation_sleep',
        component: 'personalschedule'
    },
    {
        key: 'validation_busy',
        component: 'personalschedule'
    },
    {
        key: 'validation_free',
        component: 'personalschedule'
    },
    ];

    var ScheduleSettingString = '';
    var ReadinessSettingString = '';
    var SleepString = '';
    var BusyString = '';
    var FreeString = '';

    Str.get_strings(strings).then(function(results) {
        ScheduleSettingString = results[0];
        ReadinessSettingString = results[1];
        SleepString = results[2];
        BusyString = results[3];
        FreeString = results[4];
        return true;
    });

    /**
     * @returns {array} 2D array, where the first key is Day Index, the second key is
     * Period Index, and the value is schedule status for this period.
     */
    function getScheduleData() {
        var formData = new FormData(document.querySelector("#personalscheduleform"));
        var iterator = formData.entries();
        var scheduleData = {};
        var whileState = true;
        while (whileState) {
            var result = iterator.next();
            if (result.done) {
                whileState = false;
                break;
            }
            var splittedKey = result.value[0].split(HiddenInputSeparator);
            if (splittedKey.length !== 3) {
                continue;
            }
            if (splittedKey[1] === ReadinessKeyPrefix) {
                continue;
            }

            var periodIdx = splittedKey[1];
            var dayIdx = splittedKey[2];

            if (!(dayIdx in scheduleData)) {
                scheduleData[dayIdx] = {};
            }
            scheduleData[dayIdx][periodIdx] = result.value[1];
        }
        return scheduleData;
    }

    /**
     * Checks for cells semantic equality. If the each cell describes readiness status or schedule status, then
     * returns true.
     * @param {Object} cell1
     * @param {Object} cell2
     * @returns {boolean}
     */
    function areCellsHaveSameType(cell1, cell2) {
        if (cell1.hasClass("schedule-readiness") && cell2.hasClass("schedule-readiness")) {
            return true;
        }
        if (cell1.hasClass("schedule-status") && cell2.hasClass("schedule-status")) {
            return true;
        }
        return false;
    }

    return {
        /**
         * Prevents form submission until there is at least one free period in the schedule, and
         * age is valid.
         */
        addHandlersToForm: function() {
            // Prepare modal for display in case of problems.
            var modalAge = Str.get_strings([
                {key: 'error', component: 'moodle'},
                {key: 'ageisnotvalid', component: 'personalschedule'},
            ]).then(function(strings) {
                return ModalFactory.create({
                    type: ModalFactory.types.CANCEL,
                    title: strings[0],
                    body: strings[1],
                });
            }).catch(Notification.exception);

            var modalNoFreePeriods = Str.get_strings([
                {key: 'error', component: 'moodle'},
                {key: 'nofreeperiods', component: 'personalschedule'},
            ]).then(function(strings) {
                return ModalFactory.create({
                    type: ModalFactory.types.CANCEL,
                    title: strings[0],
                    body: strings[1],
                });
            }).catch(Notification.exception);

            var form = $('#personalscheduleform');

            form.submit(function(e) {
                var ageInput = $('#age-input');
                var ageValue = ageInput.val();
                if (ageValue < AgeMin || ageValue > AgeMax) {
                    e.preventDefault();
                    return modalAge.then(function(modal) {
                        modal.show();
                        return false;
                    });
                }

                var freePeriodsCount = 0;
                var scheduleData = getScheduleData();

                for (var dayIdx in scheduleData) {
                    if (!scheduleData.hasOwnProperty(dayIdx)) {
                        continue;
                    }
                    for (var periodIdx in scheduleData[dayIdx]) {
                        if (!scheduleData[dayIdx].hasOwnProperty(periodIdx)) {
                            continue;
                        }
                        var checkStatus = scheduleData[dayIdx][periodIdx];
                        if (checkStatus === ScheduleElementTypeEnum.free) {
                            freePeriodsCount++;
                        }
                    }
                }
                if (freePeriodsCount === 0) {
                    e.preventDefault();
                    return modalNoFreePeriods.then(function(modal) {
                        modal.show();
                        return false;
                    });
                }

                return true;
            });
        },
        /**
         * Adds interaction to schedule table. User selects cells, then the popup opens. User selects specific value in the popup,
         * and then the value in the table updates.
         */
        addFunctionalToTable: function() {
            var numbersOfColumns = $("th").length - 1; // Without current column.
            $.fn.select = function() {
                var settings = $.extend({
                    children: "tbody tr",
                    className: "personal-table-selected",
                }, arguments[0] || {});

                return this.each(function(_, that) {
                    var $ch = $(this).find(settings.children),
                        sel = [],
                        last;

                    $ch.on("mousedown", function(ev) {
                        var isCtrl = (ev.ctrlKey || ev.metaKey),
                            isShift = ev.shiftKey,
                            ti = $ch.index(this),
                            li = $ch.index(last),
                            ai = $.inArray(this, sel);
                        if (isShift || isCtrl) {
                            ev.preventDefault();
                        }
                        $(sel).removeClass(settings.className);
                        $(sel).popover('destroy');

                        if (sel.length > 0 && !areCellsHaveSameType($(this), $(sel[sel.length - 1]))) {
                            isCtrl = false;
                            isShift = false;
                            sel = [];
                        }

                        if (isCtrl) {
                            if (ai > -1) {
                                sel.splice(ai, 1);
                            } else {
                                sel.push(this);
                            }
                        } else if (isShift && sel.length > 0) {
                            if (ti > li) {
                                ti = [li, li = ti][0];
                            }

                            var fromIndex = ti;
                            var toIndex = li;
                            if (fromIndex > toIndex) {
                                fromIndex = li;
                                toIndex = ti;
                            }

                            var fromColumn = fromIndex % numbersOfColumns;
                            var fromRow = Math.floor(fromIndex / numbersOfColumns);
                            var toColumn = toIndex % numbersOfColumns;
                            var toRow = Math.floor(toIndex / numbersOfColumns);
                            for (var i = fromColumn; i <= toColumn; i++) {
                                for (var j = fromRow; j <= toRow; j++) {
                                    var oneDIndex = (j * numbersOfColumns) + i;
                                    sel.push($ch[oneDIndex]);
                                }
                            }

                        } else {
                            sel = ai < 0 || sel.length > 1 ? [this] : [];
                        }

                        last = this;
                        $(sel).addClass(settings.className);
                        settings.onSelect.call(that, sel);
                    });
                });
            };

            /**
             * Toggle class on the jqueryElement, depending on specified checkStatus.
             * @param {Element} jqueryElement
             * @param {ScheduleElementTypeEnum} checkStatus
             */
            function setScheduleTableScheduleCellClass(jqueryElement, checkStatus) {
                if (checkStatus === ScheduleElementTypeEnum.sleep) {
                    jqueryElement.addClass("schedule-sleep");
                    jqueryElement.removeClass("schedule-busy");
                    jqueryElement.removeClass("schedule-free");
                } else if (checkStatus === ScheduleElementTypeEnum.busy) {
                    jqueryElement.removeClass("schedule-sleep");
                    jqueryElement.addClass("schedule-busy");
                    jqueryElement.removeClass("schedule-free");
                } else if (checkStatus === ScheduleElementTypeEnum.free) {
                    jqueryElement.removeClass("schedule-sleep");
                    jqueryElement.removeClass("schedule-busy");
                    jqueryElement.addClass("schedule-free");
                } else {
                    jqueryElement.removeClass("schedule-sleep");
                    jqueryElement.removeClass("schedule-busy");
                    jqueryElement.removeClass("schedule-free");
                }
            }

            /**
             * If the specified values equal, then returns name of the CSS class, which
             * should be add to some object.
             * @param {object} value1
             * @param {object} value2
             * @returns {string} CSS class name.
             */
            function getCheckedStateIfValueEquals(value1, value2) {
                if (value1 === value2) {
                    return "checked";
                }
                return "";
            }

            /**
             * Calculates the time, that should be spend on the course depending on free periods in the
             * schedule. Calculated time will set to specific html element.
             */
            function updateScheduledCourseDurationDays() {
                var elapsedCourseHours = parseFloat($("#total-course-duration-hours-value").val());
                var scheduledCourseDurationDays = 1;
                var scheduleData = getScheduleData();
                var atLeastOneFree = false;
                while (elapsedCourseHours > 0) {
                    for (var dayIdx in scheduleData) {
                        if (!scheduleData.hasOwnProperty(dayIdx)) {
                            continue;
                        }
                        for (var periodIdx in scheduleData[dayIdx]) {
                            if (!scheduleData[dayIdx].hasOwnProperty(periodIdx)) {
                                continue;
                            }
                            var checkStatus = scheduleData[dayIdx][periodIdx];
                            if (checkStatus === ScheduleElementTypeEnum.free) {
                                elapsedCourseHours--;
                                atLeastOneFree = true;
                            }
                        }

                        if (elapsedCourseHours <= 0) {
                            break;
                        }
                        scheduledCourseDurationDays++;
                    }

                    if (!atLeastOneFree) {
                        break;
                    }
                }

                if (atLeastOneFree) {
                    $("#scheduled-course-duration-days").text(scheduledCourseDurationDays);
                } else {
                    $("#scheduled-course-duration-days").text("-");
                }
            }

            var currentTableElements;
            var currentTableElementValue;
            var lastElement;
            var schedulingTable = $("#scheduling-table");

            schedulingTable.select({
                children: "td.schedule-selectable", // Elements to target (default: "tbody tr").
                className: "personal-table-selected", // Desired CSS class  (default: "selected").
                onSelect: function(sel) {
                    if (sel.length > 0) {
                        currentTableElements = $(sel);
                        currentTableElementValue = currentTableElements.find("span");
                        lastElement = currentTableElements.last();
                        if (lastElement.hasClass("schedule-readiness")) {
                            lastElement.popover({
                                container: "#scheduling-table",
                                html: true,
                                trigger: 'manual',
                                placement: 'bottom',
                                content:
                                    "    <input class=\"personal-table-schedule-input\" type=\"number\" step=\"0.05\"" +
                                    " min=\"0\" max=\"1\" name=\"options\"" +
                                    " id=\"schedule-input-sleep\" autocomplete=\"off\" value=\"" +
                                    currentTableElementValue.text() + "\"/>",
                                title: ReadinessSettingString,
                            });
                        } else {
                            lastElement.popover({
                                container: "#scheduling-table", html: true, trigger: 'manual', placement: 'bottom', content:
                                    "<div class=\"btn-group btn-group-toggle personal-table-btn-group\"" +
                                    " data-toggle=\"buttons\">\n" +
                                    "  <label class=\"personal-table-btn-group-label btn btn-secondary\">\n" +
                                    "    <input class=\"personal-table-schedule-input\" type=\"radio\" name=\"options\"" +
                                    " id=\"schedule-input-sleep\" autocomplete=\"off\" " +
                                    getCheckedStateIfValueEquals(currentTableElementValue.text(), ScheduleElementTypeEnum.sleep) +
                                    "> " + SleepString + "\n" +
                                    "  </label>\n" +
                                    "  <label class=\"btn btn-secondary personal-table-btn-group-label\">\n" +
                                    "    <input class=\"personal-table-schedule-input\" type=\"radio\" name=\"options\"" +
                                    " id=\"schedule-input-busy\" autocomplete=\"off\" " +
                                    getCheckedStateIfValueEquals(currentTableElementValue.text(), ScheduleElementTypeEnum.busy) +
                                    "> " + BusyString + "\n" +
                                    "  </label>\n" +
                                    "  <label class=\"btn btn-secondary personal-table-btn-group-label\">\n" +
                                    "    <input class=\"personal-table-schedule-input\" type=\"radio\" name=\"options\"" +
                                    " id=\"schedule-input-free\" autocomplete=\"off\" " +
                                    getCheckedStateIfValueEquals(currentTableElementValue.text(), ScheduleElementTypeEnum.free) +
                                    "> " + FreeString + "\n" +
                                    "  </label>\n" +
                                    "</div>", title: ScheduleSettingString,
                            });
                        }
                        lastElement.popover('show');
                    }
                }
            });

            schedulingTable.on("change", ".personal-table-schedule-input", function() {
                var hiddenInput = currentTableElements.find("input");

                if (lastElement.hasClass("schedule-readiness")) {
                    var value = $("#scheduling-table .personal-table-schedule-input").val();
                    currentTableElementValue.text(value);
                    hiddenInput.val(value);
                } else {
                    var text = $("#scheduling-table .personal-table-schedule-input:checked").attr("id");
                    if (text === "schedule-input-sleep") {
                        setScheduleTableScheduleCellClass(currentTableElements, ScheduleElementTypeEnum.sleep);
                        currentTableElementValue.text(ScheduleElementTypeEnum.sleep);
                        hiddenInput.val(ScheduleElementTypeEnum.sleep);
                    } else if (text === "schedule-input-busy") {
                        setScheduleTableScheduleCellClass(currentTableElements, ScheduleElementTypeEnum.busy);
                        currentTableElementValue.text(ScheduleElementTypeEnum.busy);
                        hiddenInput.val(ScheduleElementTypeEnum.busy);
                    } else if (text === "schedule-input-free") {
                        setScheduleTableScheduleCellClass(currentTableElements, ScheduleElementTypeEnum.free);
                        currentTableElementValue.text(ScheduleElementTypeEnum.free);
                        hiddenInput.val(ScheduleElementTypeEnum.free);
                    }
                }
                updateScheduledCourseDurationDays();
            });
        }
    };
});
