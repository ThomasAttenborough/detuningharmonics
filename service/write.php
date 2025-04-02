<?php
/*************************************************************************
         (C) Copyright AudioLabs 2017 

This source code is protected by copyright law and international treaties. This source code is made available to You subject to the terms and conditions of the Software License for the webMUSHRA.js Software. Said terms and conditions have been made available to You prior to Your download of this source code. By downloading this source code You agree to be bound by the above mentionend terms and conditions, which can also be found here: https://www.audiolabs-erlangen.de/resources/webMUSHRA. Any unauthorised use of this source code may result in severe civil and criminal penalties, and will be prosecuted to the maximum extent possible under law. 

**************************************************************************/



// Replace CSV file output with sending data to Google Sheets using Google Apps Script

// Function to sanitize strings (for folder/file names if needed)
function sanitize($string = '', $is_filename = FALSE) {
    // Replace all weird characters with dashes
    $string = preg_replace('/[^\w\-'. ($is_filename ? '~_\.' : ''). ']+/u', '-', $string);
    // Only allow one dash separator at a time (and make string lowercase)
    return strtolower(preg_replace('/--+/u', '-', $string));
}

// Function to send data payload to Google Sheets via a Google Apps Script Web App
function sendToGoogleSheet($sheet, $data) {
    // Replace with your actual Google Apps Script Web App URL
    $webAppUrl = 'https://script.google.com/macros/s/AKfycbyrBpU51NzLq_BY50lL5fsvwbwZsqmLopfC8L6ZTrFECdfdrzyG4EPmAF4lgP73t_3s/exec';
    
    $payload = array(
        "sheet" => $sheet,
        "data" => $data
    );
    
    $ch = curl_init($webAppUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

// Retrieve and decode session JSON from POST
$sessionParam = null;

// For PHP 8 and later, get_magic_quotes_gpc is removed so we do:
$sessionParam = isset($_POST['sessionJSON']) ? $_POST['sessionJSON'] : '';

if (!$sessionParam) {
    echo "No session JSON provided.";
    exit;
}

$session = json_decode($sessionParam);
if (!$session) {
    echo "Error decoding session JSON.";
    exit;
}

// Instead of setting up file paths, we will now build data arrays for each trial type.
$length = count($session->participant->name);

//----------------------
// MUSHRA Data
//----------------------
$write_mushra = false;
$mushraCsvData = array();

// Build header row
$input = array("session_test_id");
for($i = 0; $i < $length; $i++){
    array_push($input, $session->participant->name[$i]);
}
array_push($input, "session_uuid", "trial_id", "rating_stimulus", "rating_score", "rating_time", "rating_comment");
array_push($mushraCsvData, $input);

// Process each trial of type "mushra"
foreach ($session->trials as $trial) {
    if ($trial->type == "mushra") {
        $write_mushra = true;
        foreach ($trial->responses as $response) {
            $results = array($session->testId);
            for($i = 0; $i < $length; $i++){
                array_push($results, $session->participant->response[$i]);
            }
            array_push($results, $session->uuid, $trial->id, $response->stimulus, $response->score, $response->time, $response->comment);
            array_push($mushraCsvData, $results);
        }
    }
}

// If there is mushra data, send it to Google Sheets
if ($write_mushra) {
    $resp = sendToGoogleSheet("mushra", $mushraCsvData);
    // Optional: echo or log the response
    echo "MUSHRA: " . $resp . "\n";
}

//----------------------
// Paired Comparison Data
//----------------------
$write_pc = false;
$pcCsvData = array();
$input = array("session_test_id");
for($i = 0; $i < $length; $i++){
    array_push($input, $session->participant->name[$i]);
}
array_push($input, "trial_id", "choice_reference", "choice_non_reference", "choice_answer", "choice_time", "choice_comment");
array_push($pcCsvData, $input);

foreach ($session->trials as $trial) {
    if ($trial->type == "paired_comparison") {
        foreach ($trial->responses as $response) {
            $write_pc = true;
            $results = array($session->testId);
            for($i = 0; $i < $length; $i++){
                array_push($results, $session->participant->response[$i]);
            }
            array_push($results, $trial->id, $response->reference, $response->nonReference, $response->answer, $response->time, $response->comment);
            array_push($pcCsvData, $results);
        }
    }
}
if ($write_pc) {
    $resp = sendToGoogleSheet("paired_comparison", $pcCsvData);
    echo "Paired Comparison: " . $resp . "\n";
}

//----------------------
// BS1116 Data
//----------------------
$write_bs1116 = false;
$bs1116CsvData = array();
$input = array("session_test_id");
for($i = 0; $i < $length; $i++){
    array_push($input, $session->participant->name[$i]);
}
array_push($input, "trial_id", "rating_reference", "rating_non_reference", "rating_reference_score", "rating_non_reference_score", "rating_time", "rating_comment");
array_push($bs1116CsvData, $input);

foreach ($session->trials as $trial) {
    if ($trial->type == "bs1116") {
        foreach ($trial->responses as $response) {
            $write_bs1116 = true;
            $results = array($session->testId);
            for($i = 0; $i < $length; $i++){
                array_push($results, $session->participant->response[$i]);
            }
            array_push($results, $trial->id, $response->reference, $response->nonReference, $response->referenceScore, $response->nonReferenceScore, $response->time, $response->comment);
            array_push($bs1116CsvData, $results);
        }
    }
}
if ($write_bs1116) {
    $resp = sendToGoogleSheet("bs1116", $bs1116CsvData);
    echo "BS1116: " . $resp . "\n";
}

//----------------------
// LMS Data (Likert Multi Stimulus)
//----------------------
$write_lms = false;
$lmsCSVdata = array();
$input = array("session_test_id");
for($i = 0; $i < $length; $i++){
    array_push($input, $session->participant->name[$i]);
}
array_push($input, "trial_id", "stimuli_rating", "stimuli", "rating_time");
array_push($lmsCSVdata, $input);

foreach($session->trials as $trial) {
    if($trial->type == "likert_multi_stimulus") {
        foreach ($trial->responses as $response) {
            $write_lms = true; 
            $results = array($session->testId);
            for($i = 0; $i < $length; $i++){
                array_push($results, $session->participant->response[$i]);
            }
            array_push($results, $trial->id, trim($response->stimulusRating), $response->stimulus, $response->time);
            array_push($lmsCSVdata, $results);
        }
    }
}
if($write_lms){
    $resp = sendToGoogleSheet("lms", $lmsCSVdata);
    echo "LMS: " . $resp . "\n";
}

//----------------------
// LSS Data (Likert Single Stimulus)
//----------------------
$write_lss = false;
$lssCSVdata = array();
$input = array("session_test_id");
for($i = 0; $i < $length; $i++){
    array_push($input, $session->participant->name[$i]);
}
array_push($input, "trial_id");

// Determine header for stimulus ratings (could be multiple)
$ratingCount = count($session->trials[0]->responses[0]->stimulusRating);
if($ratingCount > 1) {
    for($i = 0; $i < $ratingCount; $i++){
        array_push($input, "stimuli_rating" . ($i+1));
    }
} else {
    array_push($input, "stimuli_rating");
}
array_push($input, "stimuli", "rating_time");
array_push($lssCSVdata, $input);

foreach($session->trials as $trial) {
    if($trial->type == "likert_single_stimulus") {
        foreach ($trial->responses as $response) {
            $write_lss = true; 
            $results = array($session->testId);
            for($i = 0; $i < $length; $i++){
                array_push($results, $session->participant->response[$i]);
            }
            array_push($results, $trial->id);
            $results = array_merge($results, $response->stimulusRating);
            array_push($results, $response->stimulus, $response->time);
            array_push($lssCSVdata, $results);
        }
    }
}
if($write_lss){
    $resp = sendToGoogleSheet("lss", $lssCSVdata);
    echo "LSS: " . $resp . "\n";
}

//----------------------
// Spatial Localization Data
//----------------------
$write_spatial_localization = false;
$spatial_localizationData = array();
$input = array("session_test_id");
for($i = 0; $i < $length; $i++){
    array_push($input, $session->participant->name[$i]);
}
array_push($input, "trial_id", "name", "stimulus", "position_x", "position_y", "position_z");
array_push($spatial_localizationData, $input);

foreach ($session->trials as $trial) {
    if ($trial->type == "localization") {
        foreach ($trial->responses as $response) {
            $write_spatial_localization = true;
            $results = array($session->testId);
            for($i = 0; $i < $length; $i++){
                array_push($results, $session->participant->response[$i]);
            }
            array_push($results, $trial->id, $response->name, $response->stimulus, $response->position[0], $response->position[1], $response->position[2]);
            array_push($spatial_localizationData, $results);
        }
    }
}
if ($write_spatial_localization) {
    $resp = sendToGoogleSheet("spatial_localization", $spatial_localizationData);
    echo "Spatial Localization: " . $resp . "\n";
}

//----------------------
// Spatial ASW Data
//----------------------
$write_spatial_asw = false;
$spatial_aswData = array();
$input = array("session_test_id");
for($i = 0; $i < $length; $i++){
    array_push($input, $session->participant->name[$i]);
}
array_push($input, "trial_id", "name", "stimulus", 
    "position_outerRight_x", "position_outerRight_y", "position_outerRight_z", 
    "position_innerRight_x", "position_innerRight_y", "position_innerRight_z", 
    "position_innerLeft_x", "position_innerLeft_y", "position_innerLeft_z", 
    "position_outerLeft_x", "position_outerLeft_y", "position_outerLeft_z");
array_push($spatial_aswData, $input);

foreach ($session->trials as $trial) {
    if ($trial->type == "asw") {
        foreach ($trial->responses as $response) {
            $write_spatial_asw = true;
            $results = array($session->testId);
            for($i = 0; $i < $length; $i++){
                array_push($results, $session->participant->response[$i]);
            }
            array_push($results, $trial->id, $response->name, $response->stimulus, 
                $response->position_outerRight[0], $response->position_outerRight[1], $response->position_outerRight[2], 
                $response->position_innerRight[0], $response->position_innerRight[1], $response->position_innerRight[2], 
                $response->position_innerLeft[0], $response->position_innerLeft[1], $response->position_innerLeft[2], 
                $response->position_outerLeft[0], $response->position_outerLeft[1], $response->position_outerLeft[2]);
            array_push($spatial_aswData, $results);
        }
    }
}
if ($write_spatial_asw) {
    $resp = sendToGoogleSheet("spatial_asw", $spatial_aswData);
    echo "Spatial ASW: " . $resp . "\n";
}

//----------------------
// Spatial HWD Data
//----------------------
$write_spatial_hwd = false;
$spatial_hwdData = array();
$input = array("session_test_id");
for($i = 0; $i < $length; $i++){
    array_push($input, $session->participant->name[$i]);
}
array_push($input, "trial_id", "name", "stimulus", 
    "position_outerRight_x", "position_outerRight_y", "position_outerRight_z", 
    "position_innerRight_x", "position_innerRight_y", "position_innerRight_z", 
    "position_innerLeft_x", "position_innerLeft_y", "position_innerLeft_z", 
    "position_outerLeft_x", "position_outerLeft_y", "position_outerLeft_z", 
    "height", "depth");
array_push($spatial_hwdData, $input);

foreach ($session->trials as $trial) {
    if ($trial->type == "hwd") {
        foreach ($trial->responses as $response) {
            $write_spatial_hwd = true;
            $results = array($session->testId);
            for($i = 0; $i < $length; $i++){
                array_push($results, $session->participant->response[$i]);
            }
            array_push($results, $trial->id, $response->name, $response->stimulus, 
                $response->position_outerRight[0], $response->position_outerRight[1], $response->position_outerRight[2], 
                $response->position_innerRight[0], $response->position_innerRight[1], $response->position_innerRight[2], 
                $response->position_innerLeft[0], $response->position_innerLeft[1], $response->position_innerLeft[2], 
                $response->position_outerLeft[0], $response->position_outerLeft[1], $response->position_outerLeft[2], 
                $response->height, $response->depth);
            array_push($spatial_hwdData, $results);
        }
    }
}
if ($write_spatial_hwd) {
    $resp = sendToGoogleSheet("spatial_hwd", $spatial_hwdData);
    echo "Spatial HWD: " . $resp . "\n";
}

//----------------------
// Spatial LEV Data
//----------------------
$write_spatial_lev = false;
$spatial_levData = array();
$input = array("session_test_id");
for($i = 0; $i < $length; $i++){
    array_push($input, $session->participant->name[$i]);
}
array_push($input, "trial_id", "name", "stimulus", 
    "position_center_x", "position_center_y", "position_center_z", 
    "position_height_x", "position_height_y", "position_height_z", 
    "position_width1_x", "position_width1_y", "position_width1_z", 
    "position_width2_x", "position_width2_y", "position_width2_z");
array_push($spatial_levData, $input);

foreach ($session->trials as $trial) {
    if ($trial->type == "lev") {
        foreach ($trial->responses as $response) {
            $write_spatial_lev = true;
            $results = array($session->testId);
            for($i = 0; $i < $length; $i++){
                array_push($results, $session->participant->response[$i]);
            }
            array_push($results, $trial->id, $response->name, $response->stimulus, 
                $response->position_center[0], $response->position_center[1], $response->position_center[2], 
                $response->position_height[0], $response->position_height[1], $response->position_height[2], 
                $response->position_width1[0], $response->position_width1[1], $response->position_width1[2], 
                $response->position_width2[0], $response->position_width2[1], $response->position_width2[2]);
            array_push($spatial_levData, $results);
        }
    }
}
if ($write_spatial_lev) {
    $resp = sendToGoogleSheet("spatial_lev", $spatial_levData);
    echo "Spatial LEV: " . $resp . "\n";
}

?>
