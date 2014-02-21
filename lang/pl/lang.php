<?php
$lang['bds'] = 'Baza Doskonalenia Systemu';
$lang['bds_short'] = 'BDS';
$lang['bds_timeline'] = 'Historia';
$lang['bds_issues'] = 'Problemy i zadania';
$lang['bds_issue_report'] = 'Zgłoś problem';
$lang['bds_reports'] = 'Raporty';

$lang['issues'] = 'Problemy';
$lang['tasks'] = 'Zadania';

$lang['report_issue'] = 'Zgłoś problem';
$lang['id'] = 'Nr';
$lang['_id'] = 'Nr';
$lang['type'] = 'Typ';
$lang['title'] = 'Tytuł';
$lang['state'] = 'Status';
$lang['reporter'] = 'Zgłaszający';
$lang['executor'] = 'Wykonawca';
$lang['coordinator'] = 'Koordynator';
$lang['description'] = 'Opis';
$lang['date'] = 'Utworzone';
$lang['last_mod_date'] = 'Ostatnio zmienione';
$lang['opened_for'] = 'Otwarte od';
$lang['last_modified'] = 'Ostatnio zmienione';
$lang['last_modified_by'] = 'Ostatnio zmieniony przez';

$lang['entity'] = 'Podmiot';

$lang['opinion'] = 'Ocena skuteczności';
$lang['root_cause'] = 'Przyczyna źródłowa';

$lang['save'] = 'Zapisz';
$lang['proposal'] = 'propozycja';
$lang['reported_by'] = 'zgłoszona przez';
$lang['executor_not_specified'] = 'nie przypisany';
$lang['account_removed'] = 'konto usunięte';
$lang['none'] = 'brak';

$lang['changes_history'] = 'Historia zmian';
$lang['add_comment'] = 'Dodaj komentarz';
$lang['add_task'] = 'Dodaj zadanie';
$lang['change_issue'] = 'Zmień zgłoszenie';

$lang['changed'] = 'Zmieniono';
$lang['changed_field'] = 'zmieniono';
$lang['by'] = 'przez';
$lang['from'] = 'z';
$lang['to'] = 'na';
$lang['diff'] = 'różnice';
$lang['comment'] = 'Skomentuj';
$lang['replay'] = 'Odpowiedz';
$lang['edit'] = 'Edytuj';
$lang['change_task_state'] = 'Zmień status zadania';
$lang['replay_to'] = 'Odpowiedź na';
$lang['quoted_in'] = 'Odpowiedzi';

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


$lang['type_client_complaint'] = 'reklamacja od klienta';
$lang['type_noneconformity'] = 'niezgodność';
$lang['type_supplier_complaint'] = 'reklamacja do dostawcy';

$lang['state_proposal'] = 'propozycja';
$lang['state_opened'] = 'otwarta';
$lang['state_rejected'] = 'odrzucona';
$lang['state_effective'] = 'skutecznie zamknięta';
$lang['state_ineffective'] = 'nieskutecznie zamknięta';


$lang['just_now'] = 'przed chwilą';
$lang['seconds'] = 'sek.';
$lang['minutes'] = 'min.';
$lang['hours'] = 'godz.';
$lang['days'] = 'dn.';
$lang['ago'] = 'temu';

$lang['issue_closed'] = 'Działanie zostało zamknięte %d, przez %u, dalsze zmiany nie są już możliwe.';
$lang['reopen_issue'] = 'Zmień status problemu';
$lang['add'] = 'Dodaj';

$lang['class'] = 'Klasa';

$lang['open'] = 'Otwarte';
$lang['closed'] = 'Zamknięte';

$lang['cost'] = 'Koszt(zł)';
$lang['executor'] = 'Wykonawca';

$lang['task_state'] = 'Status';
$lang['reason'] = 'Powód zmiany statusu';

$lang['task_added'] = 'Zadanie dodane';
$lang['task_changed'] = 'Zadanie zmienione';
$lang['task_rejected_header'] = 'Zadanie odrzucone';
$lang['task_closed'] = 'Zadanie zakończone';
$lang['task_reopened'] = 'Zadanie ponownie otwarte';
$lang['comment_added'] = 'Komentarz dodany';
$lang['comment_changed'] = 'Komentarz zmieniony';

$lang['replay_by_task'] = 'Odpowiedz zadaniem';
$lang['change_made'] = 'Zmiana wprowadzona';

$lang['change_comment'] = 'Zmodyfikuj komentarz';
$lang['change_comment_button'] = 'Popraw komentarz';
$lang['change_task'] = 'Zmodyfikuj zadanie';
$lang['change_task_button'] = 'Zmień zadanie';

$lang['preview'] = 'starsze';
$lang['next'] = 'nowsze';

$lang['version'] = 'Wersja';

$lang['comment_noun'] = 'Komentarz';
$lang['change'] = 'Zmiana';
$lang['task'] = 'Zadanie';

$lang['change_state_button'] = 'Zmień status';


$lang['correction'] = 'Korekcja';
$lang['corrective_action'] = 'Działania korygujące';
$lang['preventive_action'] = 'Działania zapobiegawcze';

$lang['none_comment'] = 'brak(komentarz)';
$lang['manpower'] = 'Ludzie';
$lang['method'] = 'Metoda';
$lang['machine'] = 'Maszyna';
$lang['material'] = 'Materiał';
$lang['managment'] = 'Zarządzanie';
$lang['measurement'] = 'Pomiar';
$lang['money'] = 'Pieniądze';
$lang['environment'] = 'Środowisko';

$lang['task_opened'] = 'Otwarte';
$lang ['task_done'] = 'Wykonane';
$lang ['task_rejected'] = 'Odrzucone';

$lang['reason_reopen'] = 'Przyczyna ponownego otwarcia'; 
$lang['reason_done']  = 'Przyczyna zakończenia';
$lang['reason_reject'] = 'Przyczyna odrzucenia';

$lang['issue_created'] = 'Utworzono zgłoszenie';

$lang['issue_closed'] = 'Zamknięto problem';
$lang['issue_reopened'] = 'Ponownie otwarto problem';

$lang['today'] = 'Dzisiaj';
$lang['yesterday'] = 'Wczoraj';

$lang['task_for'] = 'dla';
$lang['content'] = 'Opis';

$lang['8d_report'] = 'Raport 8D';
$lang['8d_report_for'] = 'dla';
$lang['open_date'] = 'Data otwarcia';
$lang['2d'] = '2D - Problem';
$lang['3d'] = '3D - Przyczyna';
$lang['4d'] = '4D - Działania korekcyjne (natychmiastowe)';
$lang['5d'] = '5D - Działania korygujące';
$lang['6d'] = '6D - Działania zapobiegawcze';
$lang['7d'] = '7D - Ocena skuteczności';
$lang['8d'] = '8D - Zakończenie';

$lang['cost_total'] = 'Koszt całkowity(zł)';
$lang['true_date'] = 'Data';

$lang['newest_to_oldest'] = 'Otwarte od najnowszych do najstarszych';
$lang['issues_newest_to_oldest'] = 'Problemy otwarte od najnowszych do najstarszych';
$lang['tasks_newest_to_oldest'] = 'Zadania otwarte od najnowszych do najstarszych';

$lang['newest_than'] = 'Wszystkie nowsze niż';
$lang['issues_newest_than'] = 'Problemy nowsze niż';

$lang['my_opened_issues'] = 'Problemy otwarte w które jestem zaangażowany';
$lang['my_opened'] = 'Otwarte w które jestem zaangażowany';

$lang['me_executor'] = 'Przypisane do mnie';
$lang['task_me_executor'] = 'Zadania przypisane do mnie';

$lang['issues_newest_than'] = 'Wszystkie nowsze problemy niż';
$lang['issues_newest_than_rep'] = 'Problemy nowsze niż %d';

$lang['show'] = 'Pokaż';

$lang['by_last_activity'] = 'Otwarte według najbardziej niedawno zmienianych';
$lang['issues_by_last_activity'] = 'Problemy otwarte według najbardziej niedawno zmienianych';

$lang['ns'] = 'n.d.';
