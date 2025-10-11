<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json; charset=utf-8');

require_once 'authorisation.php';
require_once 'dbextra.php';

$usePk = check_auth('system');
$link = db_connect();

$action = reqsval('action');
$comPk = reqival('comPk');
$res = [];

// Check if user has admin privileges for the competition
if ($comPk > 0 && !is_admin('admin', $usePk, $comPk)) {
    $res['result'] = 'unauthorised';
    $res['error'] = 'You are not authorized to manage pilots for this competition';
    print json_encode($res);
    exit;
}

switch ($action) {
    case 'add_pilot':
        add_pilot_to_competition($link, $comPk, $res);
        break;
    
    case 'update_pilot':
        update_pilot_data($link, $res);
        break;
    
    case 'remove_pilot':
        remove_pilot_from_competition($link, $comPk, $res);
        break;
    
    case 'get_pilot':
        get_pilot_data($link, $res);
        break;
    
    case 'list_pilots':
        list_competition_pilots($link, $comPk, $res);
        break;
    
    case 'bulk_upload_pilots':
        bulk_upload_pilots($link, $comPk, $res);
        break;
    
    default:
        $res['result'] = 'error';
        $res['error'] = 'Invalid action. Supported actions: add_pilot, update_pilot, remove_pilot, get_pilot, list_pilots, bulk_upload_pilots';
        print json_encode($res);
        exit;
}

function add_pilot_to_competition($link, $comPk, &$res) {
    $pilPk = reqival('pilPk');
    $firstName = reqsval('firstName');
    $lastName = reqsval('lastName');
    $hgfa = reqsval('hgfa');
    $civl = reqival('civl');
    $sex = reqsval('sex');
    $nationCode = reqsval('nationCode') ?: 'AUS';
    $email = reqsval('email');
    $phone = reqsval('phone');
    $birthdate = reqsval('birthdate');
    
    // If pilPk is provided, use existing pilot
    if ($pilPk) {
        // Check if pilot exists
        $query = "SELECT pilPk FROM tblPilot WHERE pilPk = $pilPk";
        $result = mysql_query($query, $link) or json_die('Pilot check failed: ' . mysql_error());
        
        if (mysql_num_rows($result) == 0) {
            $res['result'] = 'error';
            $res['error'] = 'Pilot not found';
            print json_encode($res);
            return;
        }
    } else {
        // Create new pilot if pilPk not provided
        if (!$firstName || !$lastName || !$hgfa || !$civl || !$sex) {
            $res['result'] = 'error';
            $res['error'] = 'Required fields for new pilot: firstName, lastName, hgfa, civl, sex';
            print json_encode($res);
            return;
        }
        
        // Validate sex
        if (!in_array($sex, ['M', 'F'])) {
            $res['result'] = 'error';
            $res['error'] = 'Sex must be M or F';
            print json_encode($res);
            return;
        }
        
        // Check if pilot already exists (by HGFA or CIVL)
        $query = "SELECT pilPk FROM tblPilot WHERE pilHGFA = '$hgfa' OR pilCIVL = $civl";
        $result = mysql_query($query, $link) or json_die('Pilot existence check failed: ' . mysql_error());
        
        if (mysql_num_rows($result) > 0) {
            $row = mysql_fetch_array($result, MYSQL_ASSOC);
            $pilPk = $row['pilPk'];
        } else {
            // Create new pilot
            $query = "INSERT INTO tblPilot (pilFirstName, pilLastName, pilHGFA, pilCIVL, pilSex, pilNationCode, pilEmail, pilPhoneMobile, pilBirthdate) 
                      VALUES ('$firstName', '$lastName', '$hgfa', $civl, '$sex', '$nationCode', '$email', '$phone', '$birthdate')";
            $result = mysql_query($query, $link) or json_die('Pilot creation failed: ' . mysql_error());
            $pilPk = mysql_insert_id($link);
        }
    }
    
    // Check if already registered
    $query = "SELECT regPk FROM tblRegistration WHERE comPk = $comPk AND pilPk = $pilPk";
    $result = mysql_query($query, $link) or json_die('Registration check failed: ' . mysql_error());
    
    if (mysql_num_rows($result) > 0) {
        $res['result'] = 'error';
        $res['error'] = 'Pilot is already registered for this competition';
        print json_encode($res);
        return;
    }
    
    // Add pilot to competition
    $regarr = [];
    $regarr['pilPk'] = $pilPk;
    $regarr['comPk'] = $comPk;
    $regarr['regHours'] = reqival('regHours') ?: 200; // Default 200 hours
    $clause = "comPk=$comPk and pilPk=$pilPk";
    insertup($link, 'tblRegistration', 'regPk', $clause, $regarr);
    
    // Create default handicap entry
    $query = "SELECT hanPk FROM tblHandicap WHERE comPk = $comPk AND pilPk = $pilPk";
    $result = mysql_query($query, $link) or json_die('Handicap check failed: ' . mysql_error());
    
    if (mysql_num_rows($result) == 0) {
        $handicap = reqfval('handicap') ?: 1.0; // Default handicap of 1.0
        $query = "INSERT INTO tblHandicap (comPk, pilPk, hanHandicap) VALUES ($comPk, $pilPk, $handicap)";
        $result = mysql_query($query, $link) or json_die('Handicap insert failed: ' . mysql_error());
    }
    
    $res['result'] = 'success';
    $res['message'] = 'Pilot successfully added to competition';
    $res['data'] = ['pilPk' => $pilPk, 'comPk' => $comPk];
    print json_encode($res);
}


function update_pilot_data($link, &$res) {
    $pilPk = reqival('pilPk');
    
    if (!$pilPk) {
        $res['result'] = 'error';
        $res['error'] = 'Pilot ID (pilPk) is required';
        print json_encode($res);
        return;
    }
    
    // Check if pilot exists
    $query = "SELECT pilPk FROM tblPilot WHERE pilPk = $pilPk";
    $result = mysql_query($query, $link) or json_die('Pilot check failed: ' . mysql_error());
    
    if (mysql_num_rows($result) == 0) {
        $res['result'] = 'error';
        $res['error'] = 'Pilot not found';
        print json_encode($res);
        return;
    }
    
    // Build update query with provided fields
    $updateFields = [];
    $fields = ['firstName' => 'pilFirstName', 'lastName' => 'pilLastName', 'hgfa' => 'pilHGFA', 
               'civl' => 'pilCIVL', 'sex' => 'pilSex', 'nationCode' => 'pilNationCode',
               'email' => 'pilEmail', 'phone' => 'pilPhoneMobile', 'birthdate' => 'pilBirthdate',
               'address' => 'pilAddress', 'phoneHome' => 'pilPhoneHome', 'yearStarted' => 'pilYearStarted',
               'sponsor' => 'pilSponsor', 'inlandHours' => 'pilInlandHours', 'tShirt' => 'pilTShirt'];
    
    foreach ($fields as $param => $dbField) {
        if (reqexists($param)) {
            $value = reqsval($param);
            if ($value !== '') {
                $updateFields[] = "$dbField = '$value'";
            }
        }
    }
    
    if (empty($updateFields)) {
        $res['result'] = 'error';
        $res['error'] = 'No fields to update';
        print json_encode($res);
        return;
    }
    
    $query = "UPDATE tblPilot SET " . implode(', ', $updateFields) . " WHERE pilPk = $pilPk";
    $result = mysql_query($query, $link) or json_die('Pilot update failed: ' . mysql_error());
    
    $res['result'] = 'success';
    $res['message'] = 'Pilot data updated successfully';
    $res['data'] = ['pilPk' => $pilPk];
    print json_encode($res);
}

function remove_pilot_from_competition($link, $comPk, &$res) {
    $pilPk = reqival('pilPk');
    
    if (!$pilPk) {
        $res['result'] = 'error';
        $res['error'] = 'Pilot ID (pilPk) is required';
        print json_encode($res);
        return;
    }
    
    // Check if pilot is registered for this competition
    $query = "SELECT regPk FROM tblRegistration WHERE comPk = $comPk AND pilPk = $pilPk";
    $result = mysql_query($query, $link) or json_die('Registration check failed: ' . mysql_error());
    
    if (mysql_num_rows($result) == 0) {
        $res['result'] = 'error';
        $res['error'] = 'Pilot is not registered for this competition';
        print json_encode($res);
        return;
    }
    
    // Remove from registration
    $query = "DELETE FROM tblRegistration WHERE comPk = $comPk AND pilPk = $pilPk";
    $result = mysql_query($query, $link) or json_die('Registration removal failed: ' . mysql_error());
    
    // Remove handicap entry
    $query = "DELETE FROM tblHandicap WHERE comPk = $comPk AND pilPk = $pilPk";
    $result = mysql_query($query, $link) or json_die('Handicap removal failed: ' . mysql_error());
    
    $res['result'] = 'success';
    $res['message'] = 'Pilot successfully removed from competition';
    $res['data'] = ['pilPk' => $pilPk, 'comPk' => $comPk];
    print json_encode($res);
}

function get_pilot_data($link, &$res) {
    $pilPk = reqival('pilPk');
    
    if (!$pilPk) {
        $res['result'] = 'error';
        $res['error'] = 'Pilot ID (pilPk) is required';
        print json_encode($res);
        return;
    }
    
    $query = "SELECT * FROM tblPilot WHERE pilPk = $pilPk";
    $result = mysql_query($query, $link) or json_die('Pilot query failed: ' . mysql_error());
    
    if (mysql_num_rows($result) == 0) {
        $res['result'] = 'error';
        $res['error'] = 'Pilot not found';
        print json_encode($res);
        return;
    }
    
    $row = mysql_fetch_array($result, MYSQL_ASSOC);
    
    $res['result'] = 'success';
    $res['data'] = $row;
    print json_encode($res);
}

function list_competition_pilots($link, $comPk, &$res) {
    $query = "SELECT P.*, R.regHours, H.hanHandicap 
              FROM tblRegistration R 
              LEFT JOIN tblPilot P ON P.pilPk = R.pilPk 
              LEFT JOIN tblHandicap H ON H.pilPk = P.pilPk AND H.comPk = R.comPk 
              WHERE R.comPk = $comPk 
              ORDER BY P.pilLastName, P.pilFirstName";
    
    $result = mysql_query($query, $link) or json_die('Competition pilots query failed: ' . mysql_error());
    
    $pilots = [];
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
        $pilots[] = $row;
    }
    
    $res['result'] = 'success';
    $res['data'] = $pilots;
    $res['count'] = count($pilots);
    print json_encode($res);
}

function bulk_upload_pilots($link, $comPk, &$res) {
    // Check if CSV file was uploaded
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $res['result'] = 'error';
        $res['error'] = 'No CSV file uploaded or upload error occurred';
        print json_encode($res);
        return;
    }
    
    $csvFile = $_FILES['csv_file']['tmp_name'];
    $csvData = [];
    $errors = [];
    $successCount = 0;
    $skippedCount = 0;
    
    // Read and parse CSV file
    if (($handle = fopen($csvFile, "r")) !== FALSE) {
        $header = fgetcsv($handle, 1000, ",");
        
        // Validate required columns
        $requiredColumns = ['firstName', 'lastName', 'hgfa', 'civl', 'sex'];
        $missingColumns = array_diff($requiredColumns, $header);
        
        if (!empty($missingColumns)) {
            $res['result'] = 'error';
            $res['error'] = 'Missing required columns: ' . implode(', ', $missingColumns) . '. Required columns: ' . implode(', ', $requiredColumns);
            print json_encode($res);
            return;
        }
        
        $columnIndexes = array_flip($header);
        $rowNumber = 1; // Start from 1 since header is row 0
        
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $rowNumber++;
            
            // Skip empty rows
            if (empty(array_filter($data))) {
                continue;
            }
            
            // Extract data from CSV row
            $firstName = trim($data[$columnIndexes['firstName']] ?? '');
            $lastName = trim($data[$columnIndexes['lastName']] ?? '');
            $hgfa = trim($data[$columnIndexes['hgfa']] ?? '');
            $civl = trim($data[$columnIndexes['civl']] ?? '');
            $sex = trim($data[$columnIndexes['sex']] ?? '');
            $nationCode = trim($data[$columnIndexes['nationCode']] ?? 'AUS');
            $email = trim($data[$columnIndexes['email']] ?? '');
            $phone = trim($data[$columnIndexes['phone']] ?? '');
            $birthdate = trim($data[$columnIndexes['birthdate']] ?? '');
            $regHours = trim($data[$columnIndexes['regHours']] ?? '200');
            $handicap = trim($data[$columnIndexes['handicap']] ?? '1.0');
            
            // Validate required fields
            if (empty($firstName) || empty($lastName) || empty($hgfa) || empty($civl) || empty($sex)) {
                $errors[] = "Row $rowNumber: Missing required fields (firstName, lastName, hgfa, civl, sex)";
                $skippedCount++;
                continue;
            }
            
            // Validate sex
            if (!in_array(strtoupper($sex), ['M', 'F'])) {
                $errors[] = "Row $rowNumber: Invalid sex value '$sex'. Must be 'M' or 'F'";
                $skippedCount++;
                continue;
            }
            
            // Validate CIVL (should be numeric)
            if (!is_numeric($civl)) {
                $errors[] = "Row $rowNumber: CIVL must be numeric, got '$civl'";
                $skippedCount++;
                continue;
            }
            
            // Validate regHours (should be numeric)
            if (!is_numeric($regHours)) {
                $regHours = 200; // Default value
            }
            
            // Validate handicap (should be numeric)
            if (!is_numeric($handicap)) {
                $handicap = 1.0; // Default value
            }
            
            // Process pilot registration
            $pilotResult = process_pilot_registration($link, $comPk, $firstName, $lastName, $hgfa, $civl, $sex, $nationCode, $email, $phone, $birthdate, $regHours, $handicap);
            
            if ($pilotResult['success']) {
                $successCount++;
            } else {
                $errors[] = "Row $rowNumber: " . $pilotResult['error'];
                $skippedCount++;
            }
        }
        
        fclose($handle);
    } else {
        $res['result'] = 'error';
        $res['error'] = 'Could not read CSV file';
        print json_encode($res);
        return;
    }
    
    // Prepare response
    $res['result'] = 'success';
    $res['message'] = "Bulk upload completed. Successfully processed: $successCount, Skipped: $skippedCount";
    $res['data'] = [
        'success_count' => $successCount,
        'skipped_count' => $skippedCount,
        'total_processed' => $successCount + $skippedCount,
        'errors' => $errors
    ];
    
    print json_encode($res);
}

function process_pilot_registration($link, $comPk, $firstName, $lastName, $hgfa, $civl, $sex, $nationCode, $email, $phone, $birthdate, $regHours, $handicap) {
    // Escape strings for SQL
    $firstName = mysql_real_escape_string($firstName);
    $lastName = mysql_real_escape_string($lastName);
    $hgfa = mysql_real_escape_string($hgfa);
    $civl = intval($civl);
    $sex = mysql_real_escape_string(strtoupper($sex));
    $nationCode = mysql_real_escape_string($nationCode);
    $email = mysql_real_escape_string($email);
    $phone = mysql_real_escape_string($phone);
    $birthdate = mysql_real_escape_string($birthdate);
    $regHours = intval($regHours);
    $handicap = floatval($handicap);
    
    // Check if pilot already exists (by HGFA or CIVL)
    $query = "SELECT pilPk FROM tblPilot WHERE pilHGFA = '$hgfa' OR pilCIVL = $civl";
    $result = mysql_query($query, $link) or json_die('Pilot existence check failed: ' . mysql_error());
    
    if (mysql_num_rows($result) > 0) {
        $row = mysql_fetch_array($result, MYSQL_ASSOC);
        $pilPk = $row['pilPk'];
    } else {
        // Create new pilot
        $query = "INSERT INTO tblPilot (pilFirstName, pilLastName, pilHGFA, pilCIVL, pilSex, pilNationCode, pilEmail, pilPhoneMobile, pilBirthdate) 
                  VALUES ('$firstName', '$lastName', '$hgfa', $civl, '$sex', '$nationCode', '$email', '$phone', '$birthdate')";
        $result = mysql_query($query, $link) or json_die('Pilot creation failed: ' . mysql_error());
        $pilPk = mysql_insert_id($link);
    }
    
    // Check if already registered for this competition
    $query = "SELECT regPk FROM tblRegistration WHERE comPk = $comPk AND pilPk = $pilPk";
    $result = mysql_query($query, $link) or json_die('Registration check failed: ' . mysql_error());
    
    if (mysql_num_rows($result) > 0) {
        return ['success' => false, 'error' => 'Pilot is already registered for this competition'];
    }
    
    // Add pilot to competition
    $regarr = [];
    $regarr['pilPk'] = $pilPk;
    $regarr['comPk'] = $comPk;
    $regarr['regHours'] = $regHours;
    $clause = "comPk=$comPk and pilPk=$pilPk";
    insertup($link, 'tblRegistration', 'regPk', $clause, $regarr);
    
    // Create default handicap entry
    $query = "SELECT hanPk FROM tblHandicap WHERE comPk = $comPk AND pilPk = $pilPk";
    $result = mysql_query($query, $link) or json_die('Handicap check failed: ' . mysql_error());
    
    if (mysql_num_rows($result) == 0) {
        $query = "INSERT INTO tblHandicap (comPk, pilPk, hanHandicap) VALUES ($comPk, $pilPk, $handicap)";
        $result = mysql_query($query, $link) or json_die('Handicap insert failed: ' . mysql_error());
    }
    
    return ['success' => true, 'pilPk' => $pilPk];
}

?>
