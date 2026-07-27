<?php

return [
    // Button labels
    'actions_button' => 'アクション',
    'filters_button' => 'フィルター',
    'columns_button' => '列',

    // Header labels
    'exports_header' => 'エクスポート',
    'bulk_actions_header' => '一括操作',
    'add_filter_header' => 'フィルター追加',
    'toggle_columns_header' => '列の表示切替',

    // Search and filters
    // Confirmation dialog
    'confirm_action_title' => 'アクションの確認',
    'confirm_action_message' => 'この操作を実行してもよろしいですか？',
    'confirm_action_yes' => 'はい',
    'confirm_action_cancel' => 'キャンセル',

    'search_placeholder' => '検索...',
    'select_placeholder' => 'オプションを選択...',
    'select_options_placeholder' => 'オプションを選択...',
    'search_options_placeholder' => 'オプションを検索...',
    'no_option_found' => 'オプションが見つかりません',
    'pick_a_date' => '日付を選択',
    'pick_dates' => '期間を選択',
    'active_filters' => '有効なフィルター',
    'no_results_found' => '結果が見つかりません',
    'between_values_and' => 'と',

    // Row selection
    'select_all' => 'すべて選択',
    'select_row' => '行を選択',
    'selected_count' => ':count件選択',
    'clear_selection' => '選択をクリア',
    'one_row_selected' => '1行選択',
    'all_rows_selected' => '全:total行を選択',
    'selected_rows' => ':total行中:count行を選択',
    'no_rows_selected' => '行が選択されていません',

    // Sorting
    'sort_asc' => '昇順',
    'sort_desc' => '降順',

    // Column actions
    'stick' => '列を固定',
    'unstick' => '固定解除',
    'hide_column' => '列を非表示',

    // Pagination
    'current_page_of_last' => ':total 件中 :from から :to を表示',
    'current_page' => ':current ページ',
    'rows_per_page' => 'ページあたりの行数',

    // Dialog actions
    'action_failed_dialog_title' => 'アクション失敗',
    'action_failed_dialog_message' => 'アクションを完了できませんでした。もう一度お試しください。',
    'action_failed_dialog_button' => 'OK',
    'export_processing_dialog_button' => '処理中...',

    // Export modal and actions
    'action_export' => 'エクスポート',
    'export_modal_title' => 'エクスポート',
    'export_modal_subtitle' => 'エクスポートする列とファイル形式を選択してください。',
    'export_exporting_title' => 'エクスポートを準備しています',
    'export_exporting_message' => 'ファイルの準備ができると、自動的にダウンロードが始まります。',
    'export_modal_columns_header' => '列',
    'export_select_all' => 'すべて選択',
    'export_deselect_all' => 'すべて解除',
    'export_formats_header' => 'ファイル形式',
    'export_format_csv' => 'CSV',
    'export_format_xlsx' => 'Excel (XLSX)',
    'export_cancel_button' => 'キャンセル',
    'export_submit_button' => 'エクスポート',
    'export_started_toast_title' => ':model のエクスポートを開始しました',
    'export_started_toast_body' => '{1} :count 件の行をバックグラウンドで処理します。完了時に通知します。|[2,*] :count 件の行をバックグラウンドで処理します。完了時に通知します。',
    'export_completed_toast_title' => ':model のエクスポートが完了しました',
    'export_completed_toast_body' => '{1} :count 件の行をエクスポートしました。|[2,*] :count 件の行をエクスポートしました。',
    'export_failed_rows_toast_body' => '{1} :count 件の行のエクスポートに失敗しました。|[2,*] :count 件の行のエクスポートに失敗しました。',
    'export_download_csv' => 'CSV をダウンロード',
    'export_download_xlsx' => 'Excel をダウンロード',
    'export_max_rows_message' => 'このエクスポートは最大 :max 行までです（:count 行が要求されました）。',
    'export_no_columns_message' => 'エクスポートする列を 1 つ以上選択してください。',
    'export_file_name' => 'export-:export_id-:model',

    // Row action labels
    'action_view' => '表示',
    'action_edit' => '編集',
    'action_delete' => '削除',
    'action_send_for_approval' => '承認申請',

    // Loading states
    'loading_placeholder' => '読み込み中...',

    // Empty states
    'empty_title' => 'データが見つかりません',
    'empty_message' => '表示するレコードがありません。',

    // Table states
    'actions' => 'アクション',
    'yes' => 'はい',
    'no' => 'いいえ',

    // Common action labels
    'action_view' => '表示',
    'action_edit' => '編集',
    'action_delete' => '削除',
    'action_approve' => '承認',
    'action_reject' => '却下',
    'action_cancel' => 'キャンセル',
    'action_view_pdf' => 'PDF表示',
    'action_send_email' => 'メール送信',
    'action_duplicate' => '複製',
    'action_send_for_approval' => '承認依頼',
    'action_toggle_status' => 'ステータス切替',
    'action_upload_to_dropbox' => 'Dropboxにアップロード',
    'action_create_meet' => 'ミーティング作成',
    'action_create_employee' => '従業員作成',
    'action_create_invoice' => '請求書作成',
    'action_manage' => '管理',
    'action_export' => 'エクスポート',

    // Filter clauses
    'clause_equals' => '等しい',
    'clause_not_equals' => '等しくない',
    'clause_contains' => '含む',
    'clause_not_contains' => '含まない',
    'clause_starts_with' => '～で始まる',
    'clause_not_starts_with' => '～で始まらない',
    'clause_ends_with' => '～で終わる',
    'clause_not_ends_with' => '～で終わらない',
    'clause_greater_than' => 'より大きい',
    'clause_greater_than_or_equal' => '以上',
    'clause_less_than' => 'より小さい',
    'clause_less_than_or_equal' => '以下',
    'clause_between' => '～の間',
    'clause_not_between' => '～の間でない',
    'clause_in' => '含まれる',
    'clause_not_in' => '含まれない',
    'clause_is_null' => 'null',
    'clause_is_not_null' => 'nullでない',
    'clause_is_set' => '設定済み',
    'clause_is_not_set' => '未設定',
    'clause_is_true' => '真',
    'clause_is_false' => '偽',
    'clause_before' => '～より前',
    'clause_equal_or_before' => '～以前',
    'clause_after' => '～より後',
    'clause_equal_or_after' => '～以降',
    'clause_with_trashed' => '削除済みを含む',
    'clause_only_trashed' => '削除済みのみ',
    'clause_without_trashed' => '削除済みを除く',

    // Legacy keys (for backward compatibility during transition)
    'All' => 'すべて',
    'Search' => '検索',
    'Filter' => 'フィルター',
    'Actions' => 'アクション',
    'No results found' => '結果が見つかりません',
    'Showing' => '表示中',
    'to' => '～',
    'of' => '/',
    'results' => '件',
    'Previous' => '前へ',
    'Next' => '次へ',
];
