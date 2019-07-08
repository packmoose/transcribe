<?php

$file = $argv[1];
$audiofile = isset($argv[2]) ? $argv[2] : "[INSERT FILENAME]";
$starttime = isset($argv[3]) ? $argv[3] : 0;
$interview = isset($argv[4]) ? $argv[4] : false;
$data = json_decode(file_get_contents($file));
$basename = basename($file);

// Load up speaker labels.
if($data->results->speaker_labels->segments != null) {
  $labels = $data->results->speaker_labels->segments;
  $speaker_start_times = [];
  foreach ($labels as $label) {
    foreach ($label->items as $item) {
      $speaker_start_times[number_format($item->start_time, 3)] = $label->speaker_label;
    }
  }
}

//set file end time
$result_item = end($data->results->items);
while ($result_item->type != "pronunciation") { 
  $result_item = prev($data->results->items); 
}
$file_end = $starttime + number_format($result_item->end_time, 3, '.', '');
reset($data->results->items);


// Now we iterate through items and build the transcript
$items = $data->results->items;
$lines = [];
$line = '';
$time = 0;
$speaker = NULL;
foreach ($items as $item) {
  $content = $item->alternatives[0]->content;
  if (property_exists($item, 'start_time')) {
    $current_speaker = $speaker_start_times[number_format($item->start_time, 3)];
  }
  elseif ($item->type == 'punctuation') {
    $line .= $content;
  }
  if ($current_speaker != $speaker) {
    if ($speaker) {
      $lines[] = [
        'speaker' => $speaker,
        'line' => $line,
        'time' => $time,
      ];
    }
    $line = $content;
    $speaker = $current_speaker;
    $time = number_format($item->start_time, 3, '.', '');
  }
  elseif ($item->type != 'punctuation') {
    $line .= ' ' . $content;
  }
}
// Record the last line since there was no speaker change.
$lines[] = [
  'speaker' => $speaker,
  'line' => $line,
  'time' => $time,
];


// Finally, let's print out our transcript.
$fh = fopen($file . '-transcript.txt', 'w');
fputs($fh,"PROJECT NAME FROM THE GUIDE\nDESCRIPTION: USE EITHER 'DIALOGUE WITH' OR 'INTERVIEW WITH' AND LIST ALL SPEAKERS HERE FEMALE 1, MALE 1, FEMALE 2, FEMALE 3, MALE 2 (If your test contains more than one type you must list all segments or parts)\nFILE NAME: ".$audiofile."\n".date("Y/m/d")."\nTRANSCRIBED BY DAILY TRANSCRIPTION_EJP\n\n\n\n");
if($interview) {
  //in this case, we follow the 'interview' format
  foreach ($lines as $line_data) {
    if($line_data['speaker'] == "spk_0") {
      $line = "Q:  ".$line_data['line'];
    } else {
      $line = $audiofile.'     [' . gmdate('H:i:s', $line_data['time']+$starttime) . "]\n" . $line_data['speaker'] . ':  ' . $line_data['line'];
    }
    fputs($fh, $line . "\n\n");
  }
} else {
  //otherwise, we assume 'dialogue' format
  $last = 0;
  foreach ($lines as $line_data) {
    if($last === 0 || $line_data['time'] > $last + 20) {
      $prefix = $audiofile .'     ['. gmdate('H:i:s', $line_data['time']+$starttime) .']'."\n";
      $last = $line_data['time'];
    } else {
      $prefix = "";
    }
    $line = "SPEAKER ". substr($line_data['speaker'],4) . ' FIRST NAME:  ' . $line_data['line'];
    fputs($fh, $prefix.$line . "\n\n");
  }
}
fputs($fh, "\n". $audiofile ."     [" . gmdate('H:i:s', $file_end) . "]\n[END OF FILE:  ". $audiofile ."]");

fclose($fh);

/* End of the transcript.php file */
