<?php
require_once('cswGeoClient.php');

$URL_GEONETWORK_CSW_SERVICE = "http://www.donnees.gouv.qc.ca/geonetwork/srv/eng/csw";
$ERROR_MESSAGE = "Une erreur est survenue en tentant de récupérer les métadonnées...";

$url = $URL_GEONETWORK_CSW_SERVICE;
if (isset($_GET['url'])) {
	$url = urldecode($_GET['url']);
}

$query = "*";
if (isset($_GET['query'])) {
	$query = "%" . $_GET['query']  . "%";
}

// By default, query the whole wide world
$xmin = -180;
$ymin = -90;
$xmax = 180;
$ymax = 90;
if (isset($_GET['xmin'])) {
	$xmin = $_GET['xmin'];
}
if (isset($_GET['ymin'])) {
	$ymin = $_GET['ymin'];
}
if (isset($_GET['xmax'])) {
	$xmax = $_GET['xmax'];
}
if (isset($_GET['ymax'])) {
	$ymax = $_GET['ymax'];
}
$topic = "";
if (isset($_GET['topic'])) {
	$topic = $_GET['topic'];
}

//CSV examples
//for ALL
//$strComplet = $cswClient->getRecords("AnyText", "*", -180, -90, 180, 90, "full");
//by HEME
/*
$strComplet = $cswClient->getRecordsByTopic("dc:subject", "society", -180, -90, 180, 90); //=90 47 avec bbox
$strComplet = $cswClient->getRecordsByTopic("dc:subject", "economy", -180, -90, 180, 90); //=22 5 avec bbox
$strComplet = $cswClient->getRecordsByTopic("dc:subject", "farming", -180, -90, 180, 90); 
$strComplet = $cswClient->getRecordsByTopic("dc:subject", "biota", -180, -90, 180, 90); 
$strComplet = $cswClient->getRecordsByTopic("dc:subject", "health", -180, -90, 180, 90);
$strComplet = $cswClient->getRecordsByTopic("dc:subject", "boundaries", -180, -90, 180, 90);
$strComplet = $cswClient->getRecordsByTopic("dc:subject", "transportation", -180, -90, 180, 90);
$strComplet = $cswClient->getRecordsByTopic("dc:subject", "geoscientificInformation", -180, -90, 180, 90);
$strComplet = $cswClient->getRecordsByTopic("dc:subject", "imageryBaseMapsEarthCover", -180, -90, 180, 90);
$strComplet = $cswClient->getRecordsByTopic("dc:subject", "location", -180, -90, 180, 90);
*/

$cswClient = new cswGeoClient($url);
if ($topic != ""){
  $strComplet = $cswClient->getRecordsByTopic("dc:subject", $topic, $query, $xmin, $ymin, $xmax, $ymax, "full", 25);
}
else{
  $strComplet = $cswClient->getRecordsWithBBOX("AnyText", $query, $xmin, $ymin, $xmax, $ymax, "full", 25);  
}

$GetRecordsResponse = new SimpleXMLElement($strComplet);
$GetRecordsResponse->registerXPathNamespace('c', 'http://www.opengis.net/cat/csw/2.0.2');
   
$numberOfRecordsMatched = $GetRecordsResponse->xpath('/csw:GetRecordsResponse/csw:SearchResults/@numberOfRecordsMatched');
$numberOfRecordsReturned = $GetRecordsResponse->xpath('/csw:GetRecordsResponse/csw:SearchResults/@numberOfRecordsReturned');

$geojson = array(
   'type'                     => 'FeatureCollection',
   'numberOfRecordsMatched'   => (string)$numberOfRecordsMatched[0],
   'numberOfRecordsReturned'  => (string)$numberOfRecordsReturned[0],
   'features'                 => array()
);

$GetRecordsResponse->registerXPathNamespace('c', 'http://www.opengis.net/cat/csw/2.0.2');
$records = $GetRecordsResponse->xpath('//c:Record');
$ns = $GetRecordsResponse->getNamespaces(true);
$cp = 0; 
foreach ($records as $record) {
  $dc = $record->children($ns["dc"]);
  $ows = $record->children($ns["ows"]);
  $lowerCorner = $ows->BoundingBox->LowerCorner;
  $upperCorner = $ows->BoundingBox->UpperCorner;
  if (isset($lowerCorner) && isset($upperCorner)) {
    $lr = preg_split('/\s+/', $lowerCorner);
    $ul = preg_split('/\s+/', $upperCorner);
    $coordinates = array(array(array($ul[0],$lr[1]), array($ul[0],$ul[1]), array($lr[0],$ul[1]), array($lr[0], $lr[1]), array($ul[0],$lr[1])));      
  }
  else{
    $coordinates = null;
  }
  $uri = array();
  for ($i=0;$i < count($dc->URI); $i++){
    array_push($uri, (string)$dc->URI[$i]);
  }
  
  $properties = array(
         'title' => (string)$dc->title[0],
         'identifier' =>(string)$dc->identifier[0],
         'URI' => $uri,
         'abstract' => (string)$dc->abstract[0],
         'description' => (string)$dc->description[0]
  );
  
  $geometry = array(
         'type' => "Polygon",
         'coordinates' => $coordinates
  );
  
  $feature = array(
         'type' => 'Feature',
         'id' => $cp,
         'properties' => $properties,
         'geometry' => $geometry
  );
  
   array_push($geojson['features'], $feature);
   $cp += 1;
}

$response = json_encode($geojson);

// If the call come from a ajax/jsonp
if (isset($_GET['jsoncallback'])){
 $response = $_GET['jsoncallback'] . '(' . $response . ')';
}
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json; charset=utf-8');    
header("Access-Control-Allow-Origin: *");

die($response);