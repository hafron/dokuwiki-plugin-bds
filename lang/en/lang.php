<?php
$lang['bds'] = 'Base of eliminating the risks';
$lang['bds_short'] = 'BEZ';
$lang['bds_timeline'] = 'History';
$lang['bds_issues'] = 'Issues and tasks';
$lang['bds_issue_report'] = 'Report issue';
$lang['bds_reports'] = 'Reports';

$lang['issues'] = 'Issues';
$lang['tasks'] = 'Tasks';

$lang['report_issue'] = 'Report issue';
$lang['id'] = 'Id';
$lang['_id'] = 'Id';
$lang['type'] = 'Issue type';
$lang['title'] = 'Title';
$lang['state'] = 'State';
$lang['reporter'] = 'Reporter';
$lang['executor'] = 'Executor';
$lang['coordinator'] = 'Coordinator';
$lang['description'] = 'Description';
$lang['date'] = 'Created';
$lang['last_mod_date'] = 'Last modified';
$lang['opened_for'] = 'Opened for';
$lang['last_modified'] = 'Last modified';
$lang['last_modified_by'] = 'Last modified by';
$lang['opened_tasks'] = 'Opened tasks';

$lang['entity'] = 'Root cause';

$lang['opinion'] = 'Evaluation of effectiveness';
$lang['root_cause'] = 'Cause category';

$lang['save'] = 'Save';
$lang['proposal'] = 'proposal';
$lang['reported_by'] = 'reported by';
$lang['executor_not_specified'] = 'not specified';
$lang['account_removed'] = 'account removed';
$lang['none'] = 'none';

$lang['changes_history'] = 'Timeline';
$lang['add_comment'] = 'Add comment';
$lang['add_task'] = 'Add task';
$lang['change_issue'] = 'Change issue';

$lang['changed'] = 'Changed';
$lang['changed_field'] = 'changed';
$lang['by'] = 'by';
$lang['from'] = 'from';
$lang['to'] = 'to';
$lang['diff'] = 'diffs';
$lang['comment'] = 'Comment';
$lang['replay'] = 'Replay';
$lang['edit'] = 'Edit';
$lang['change_task_state'] = 'Change state of the task';
$lang['replay_to'] = 'Replay to';
$lang['quoted_in'] = 'Replays';

$lang['error_issue_id_not_specifed'] = 'Nie podałeś numeru wiersza, który chcesz odczytać.';
$lang['error_issue_id_unknown'] = 'Wiersz, który próbujesz odczytać nie istnieje.';
$lang['error_db_connection'] = 'Nie można połączyć się z bazą danych.';
$lang['error_issue_insert'] = 'Nie można dodać nowego problemu.';
$lang['error_task_add'] = 'Nie masz uprawnień aby dodawać zadania.';
$lang['error_table_unknown'] = 'Wybrana tabela nie istnieje.';
$lang['error_report_unknown'] = 'Wybrana raport nie istnieje.';

$lang['vald_type_required'] = 'Musisz podać typ problemu.';
$lang['vald_entity_required'] = 'Musisz wybrać podmiot z listy.';
$lang['vald_title_required'] = 'Musisz podać tytuł.';
$lang['vald_title_too_long'] = 'Tytuł jest za długi. Maksymalna długość tytułu wynosi: %d.';
$lang['vald_title_wrong_chars'] = 'Tytuł zawiera niedozwolone znaki. Dozwolone znaki to litery, cyfry, spacje, myślniki, kropki i przecinki.';
$lang['vald_executor_required'] = 'Musisz wybrać istniejącego użytkownika albo na razie nie przypisywać problemu do nikogo.';
$lang['vald_coordinator_required'] = 'Koordynator musi być jednocześnie moderatorem BDSa.';

$lang['vald_desc_required'] = 'Musisz podać opis problemu.';
$lang['vald_desc_too_long'] = 'Opis problemu jest za długi. Maksymalna długość opisu wynosi: %d znaków.';
$lang['vald_opinion_too_long'] = 'Ocena skuteczności jest za długa. Maksymalna długość tego pola wynosi: %d znaków.';
$lang['vald_cannot_give_opinion'] = 'Nie możesz dodać oceny skuteczności jeżeli problem pozostanie otwarty.';
$lang['vald_cannot_give_reason'] = 'Nie możesz podać powodu zmiany statusu zadania, jeżeli zadanie nie zmieni statusu.';


$lang['vald_content_required'] = 'Musisz wprowadzić jakąś treść.';
$lang['vald_content_too_long'] = 'Treść jest za długa. Maksymalna długość wynosi: %d.';
$lang['vald_replay_to_not_exists'] = 'Element historii na który próbujesz odpowiedzieć nie istnieje.';
$lang['vald_state_required'] = 'Musisz podać status problemu.';

$lang['vald_task_state_required'] = 'Zadanie musi mieć określony status.';
$lang['vald_task_state_tasks_not_closed'] = 'Nie możesz zamykać problemu do póki nie pozamykasz wszystkich zadań. Otwarte zadania: %t.';

$lang['vald_executor_not_exists'] = 'Użytkownik podany jako wykonawca nie istnieje.';
$lang['vald_cost_too_big'] = 'Koszt jest duży. Maksymalny koszt wynosi: %d';
$lang['vald_cost_wrong_format'] = 'Koszt ma zły format. Podaj prawidłową liczbę zmiennoprzecinkową.';
$lang['vald_class_required'] = 'Musisz podać klasę zadania.';

$lang['vald_days_should_be_numeric'] = 'Ilość dni musi być liczbą.';


$lang['type_complaint'] = 'complaint';
$lang['type_noneconformity'] = 'nonconformity';
$lang['type_risk'] = 'risk';

$lang['state_proposal'] = 'proposal';
$lang['state_opened'] = 'opened';
$lang['state_rejected'] = 'rejected';
$lang['state_effective'] = 'effective closed';
$lang['state_ineffective'] = 'uneffective closed';


$lang['just_now'] = 'just now';
$lang['seconds'] = 'sec.';
$lang['minutes'] = 'min.';
$lang['hours'] = 'h';
$lang['days'] = 'ds';
$lang['ago'] = 'ago';

$lang['issue_closed'] = 'Issue was closed %d, by %u, you no longer can modify it..';
$lang['reopen_issue'] = 'Change problem state';
$lang['add'] = 'Add';

$lang['class'] = 'Class';

$lang['open'] = 'Opened';
$lang['closed'] = 'Closed';

$lang['cost'] = 'Cost';
$lang['executor'] = 'Executive';

$lang['task_state'] = 'State';
$lang['reason'] = 'Reason';

$lang['task_added'] = 'Task added';
$lang['task_changed'] = 'Task changed';
$lang['task_rejected_header'] = 'Task rejected';
$lang['task_closed'] = 'Task closed';
$lang['task_reopened'] = 'Task opened again';
$lang['comment_added'] = 'Comment added';
$lang['comment_changed'] = 'Comment changed';

$lang['replay_by_task'] = 'Replay by task';
$lang['change_made'] = 'Change made';

$lang['change_comment'] = 'Modify comment';
$lang['change_comment_button'] = 'Modify comment';
$lang['change_task'] = 'Modify task';
$lang['change_task_button'] = 'Modify task';

$lang['preview'] = 'oledr';
$lang['next'] = 'newer';

$lang['version'] = 'Version';

$lang['comment_noun'] = 'Comment';
$lang['change'] = 'Change';
$lang['task'] = 'Task';

$lang['change_state_button'] = 'Change state';


$lang['correction'] = 'Correction';
$lang['corrective_action'] = 'Corrective action';
$lang['preventive_action'] = 'Preventive action';

$lang['none_comment'] = 'none(comment)';
$lang['manpower'] = 'Manpower';
$lang['method'] = 'Method';
$lang['machine'] = 'Machine';
$lang['material'] = 'Material';
$lang['managment'] = 'Managment';
$lang['measurement'] = 'Measurement';
$lang['money'] = 'Money';
$lang['environment'] = 'Enviroment';

$lang['task_opened'] = 'Opened';
$lang ['task_done'] = 'Finished';
$lang ['task_rejected'] = 'Rejected';

$lang['reason_reopen'] = 'Reopen reason'; 
$lang['reason_done']  = 'Finished reason';
$lang['reason_reject'] = 'Rejecton reason';

$lang['issue_created'] = 'Issue created';

$lang['issue_closed'] = 'Problem closed';
$lang['issue_reopened'] = 'Issue reopened';

$lang['today'] = 'Today';
$lang['yesterday'] = 'Yesterday';

$lang['task_for'] = 'for';
$lang['content'] = 'Content';

$lang['8d_report'] = '8D report';
$lang['8d_report_for'] = 'for';
$lang['open_date'] = 'Data otwarcia';
$lang['2d'] = '2D - Problem';
$lang['3d'] = '3D - Przyczyna';
$lang['4d'] = '4D - Działania korekcyjne (natychmiastowe)';
$lang['5d'] = '5D - Działania korygujące';
$lang['6d'] = '6D - Działania zapobiegawcze';
$lang['7d'] = '7D - Ocena skuteczności';
$lang['8d'] = '8D - Zakończenie';

$lang['cost_total'] = 'Final cost';
$lang['true_date'] = 'Date';

$lang['newest_to_oldest'] = 'Opened newest to oldest';
$lang['issues_newest_to_oldest'] = 'Issues opened newest to oldest';
$lang['tasks_newest_to_oldest'] = 'Task opened newest to oldest';
$lang['tasks_newest_than_rep'] = 'Tasks never than %d';

$lang['newest_than'] = 'All newer than';
$lang['issues_newest_than'] = 'Isses newer than';

$lang['my_opened_issues'] = 'Opened issues that I am involved';
$lang['my_opened'] = 'Opened that I am involved';

$lang['me_executor'] = 'Assigned to me';
$lang['task_me_executor'] = 'Tasks assigned to me';

$lang['issues_newest_than'] = 'All newer than';
$lang['issues_newest_than_rep'] = 'Issues newen than %d';

$lang['show'] = 'Show';

$lang['by_last_activity'] = 'Opened by lastest activity';
$lang['issues_by_last_activity'] = 'Issues opened by lastest activity';

$lang['ns'] = 'n.s.';

$lang['ended'] = 'Finished';
