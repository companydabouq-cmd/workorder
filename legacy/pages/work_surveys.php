<?php
require_once __DIR__ . '/../includes/functions.php'; require_staff();
$DOC='survey'; $DOC_TITLE='حصر عمل'; $LIST_TITLE='الأعمال المحصورة';
$TBL_MASTER='work_surveys'; $TBL_ITEMS='work_survey_items';
$SEQ_KEY='work_survey'; $NO_FIELD='survey_no';
$FORM_PAGE='work_survey_form.php'; $PRINT_PAGE='../reports/print_survey.php';
$PAGE_KEY='surveys'; $PAGE_TITLE='الأعمال المحصورة';
$CONVERT=[
  'flag'=>'converted_to_order','target_tbl'=>'work_orders','target_items'=>'work_order_items',
  'target_seq'=>'work_order','target_no'=>'order_no','target_doc'=>'order','src_col'=>'source_survey_id',
  'col_label'=>'حوّل لأمر عمل','menu_label'=>'تحويل إلى أمر عمل','success'=>'تم التحويل إلى أمر عمل رقم',
];
require __DIR__ . '/../includes/doc_list.php';
