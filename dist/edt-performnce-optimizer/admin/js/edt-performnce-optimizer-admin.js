/**
 * EDT Performance Optimizer — Admin JavaScript
 */
(function ($) {
    'use strict';

    $(document).ready(function () {

        // Confirm before enabling emergency rollback.
        $('form').on('submit', function () {
            var rollbackInput = $(this).find('input[name="edt_perf[emergency_rollback]"]');
            if (rollbackInput.length && rollbackInput.is(':checked')) {
                if (!confirm('Enable emergency rollback? All optimizations will be disabled immediately.')) {
                    return false;
                }
            }
        });

        // Warn if HTML comment log is enabled.
        $('input[name="edt_perf[enable_html_comment_log]"]').on('change', function () {
            if ($(this).is(':checked')) {
                alert('⚠️ HTML Comment Log is for staging/debugging only. Disable this on production to avoid exposing optimization data in page source.');
            }
        });

        // "Select All" / "Select None" helpers for asset rules table.
        $('#edt-select-all').on('click', function () {
            $('.edt-rules-table input[type="checkbox"]').prop('checked', true);
        });
        $('#edt-select-none').on('click', function () {
            $('.edt-rules-table input[type="checkbox"]').prop('checked', false);
        });

    });

})(jQuery);
