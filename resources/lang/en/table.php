<?php

return [
    // Button labels
    'actions_button' => 'Actions',
    'filters_button' => 'Filters',
    'columns_button' => 'Columns',

    // Header labels
    'exports_header' => 'Exports',
    'bulk_actions_header' => 'Bulk Actions',
    'add_filter_header' => 'Add Filter',
    'toggle_columns_header' => 'Toggle Columns',

    // Search and filters
    'search_placeholder' => 'Search...',
    'active_filters' => 'Active Filters',
    'no_results_found' => 'No results found',
    'between_values_and' => 'and',

    // Row selection
    'select_all' => 'Select All',
    'select_row' => 'Select Row',
    'selected_count' => ':count selected',
    'clear_selection' => 'Clear Selection',
    'one_row_selected' => '1 row selected',
    'all_rows_selected' => 'All :total rows selected',
    'selected_rows' => ':count of :total rows selected',
    'no_rows_selected' => 'No rows selected',

    // Sorting
    'sort_asc' => 'Sort Ascending',
    'sort_desc' => 'Sort Descending',

    // Column actions
    'stick' => 'Stick Column',
    'unstick' => 'Unstick Column',
    'hide_column' => 'Hide Column',

    // Pagination
    'current_page_of_last' => 'Showing :from to :to of :total results',
    'current_page' => 'Page :current',
    'rows_per_page' => 'Rows per page',

    // Dialog actions
    'action_failed_dialog_title' => 'Action Failed',
    'action_failed_dialog_message' => 'The action could not be completed. Please try again.',
    'action_failed_dialog_button' => 'OK',
    'export_processing_dialog_button' => 'Processing...',

    // Export modal and actions
    'action_export' => 'Export',
    'export_modal_title' => 'Export',
    'export_modal_subtitle' => 'Choose the columns and file format to export.',
    'export_exporting_title' => 'Preparing your export',
    'export_exporting_message' => 'Your download will start automatically when the file is ready.',
    'export_modal_columns_header' => 'Columns',
    'export_select_all' => 'Select all',
    'export_deselect_all' => 'Deselect all',
    'export_formats_header' => 'File format',
    'export_format_csv' => 'CSV',
    'export_format_xlsx' => 'Excel (XLSX)',
    'export_cancel_button' => 'Cancel',
    'export_submit_button' => 'Export',
    'export_started_toast_title' => ':model export started',
    'export_started_toast_body' => '{1} :count row will be processed in the background. You will be notified when it is ready.|[2,*] :count rows will be processed in the background. You will be notified when it is ready.',
    'export_completed_toast_title' => 'Your :model export is ready',
    'export_completed_toast_body' => '{1} :count row exported.|[2,*] :count rows exported.',
    'export_failed_rows_toast_body' => '{1} :count row failed to export.|[2,*] :count rows failed to export.',
    'export_download_csv' => 'Download .csv',
    'export_download_xlsx' => 'Download .xlsx',
    'export_max_rows_message' => 'This export is limited to :max rows (:count rows requested).',
    'export_no_columns_message' => 'Select at least one column to export.',
    'export_file_name' => 'export-:export_id-:model',

    // Row action labels
    'action_view' => 'View',
    'action_edit' => 'Edit',
    'action_delete' => 'Delete',
    'action_send_for_approval' => 'Send for Approval',

    // Loading states
    'loading_placeholder' => 'Loading...',

    // Empty states
    'empty_title' => 'No Data Found',
    'empty_message' => 'There are no records to display.',

    // Table states
    'actions' => 'Actions',
    'yes' => 'Yes',
    'no' => 'No',

    // Filter clauses
    'clause_equals' => 'equals',
    'clause_not_equals' => 'not equals',
    'clause_contains' => 'contains',
    'clause_not_contains' => 'not contains',
    'clause_starts_with' => 'starts with',
    'clause_not_starts_with' => 'not starts with',
    'clause_ends_with' => 'ends with',
    'clause_not_ends_with' => 'not ends with',
    'clause_greater_than' => 'greater than',
    'clause_greater_than_or_equal' => 'greater than or equal',
    'clause_less_than' => 'less than',
    'clause_less_than_or_equal' => 'less than or equal',
    'clause_between' => 'between',
    'clause_not_between' => 'not between',
    'clause_in' => 'in',
    'clause_not_in' => 'not in',
    'clause_is_null' => 'is null',
    'clause_is_not_null' => 'is not null',
    'clause_is_set' => 'is set',
    'clause_is_not_set' => 'is not set',
    'clause_is_true' => 'is true',
    'clause_is_false' => 'is false',
    'clause_before' => 'before',
    'clause_equal_or_before' => 'equal or before',
    'clause_after' => 'after',
    'clause_equal_or_after' => 'equal or after',
    'clause_with_trashed' => 'with trashed',
    'clause_only_trashed' => 'only trashed',
    'clause_without_trashed' => 'without trashed',

    // Legacy keys (for backward compatibility during transition)
    'All' => 'All',
    'Search' => 'Search',
    'Filter' => 'Filter',
    'Actions' => 'Actions',
    'No results found' => 'No results found',
    'Showing' => 'Showing',
    'to' => 'to',
    'of' => 'of',
    'results' => 'results',
    'Previous' => 'Previous',
    'Next' => 'Next',
];
