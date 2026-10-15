<?php
require_once __DIR__ . '/../includes/functions.php'; require_staff();
$DOC='prelim'; $DOC_TITLE='حصر عمل مبدئي'; $LIST_TITLE='الأعمال المبدئية المحصورة';
$TBL_MASTER='prelim_surveys'; $TBL_ITEMS='prelim_survey_items';
$SEQ_KEY='prelim_survey'; $NO_FIELD='prelim_no';
$FORM_PAGE='prelim_survey_form.php'; $PRINT_PAGE='../reports/print_prelim.php';
$PAGE_KEY='prelims'; $PAGE_TITLE='الأعمال المبدئية المحصورة';
$CONVERT=[
  'flag'=>'converted_to_survey','target_tbl'=>'work_surveys','target_items'=>'work_survey_items',
  'target_seq'=>'work_survey','target_no'=>'survey_no','target_doc'=>'survey','src_col'=>'source_prelim_id',
  'col_label'=>'حوّل لحصر عمل','menu_label'=>'تحويل إلى حصر عمل','success'=>'تم التحويل إلى حصر عمل رقم',
];
require __DIR__ . '/../includes/doc_list.php';
