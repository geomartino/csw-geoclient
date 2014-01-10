<?php

/**
 * INSTALL PEAR and package request2  http://pear.php.net/package/HTTP_Request2/
 */
//$path = '/usr/local/pear/share/pear';
//set_include_path(get_include_path() . PATH_SEPARATOR . $path);
require_once 'HTTP/Request2.php';

/**
 * cswClient allows to request a OGC CSW 2.0.2 - ISO API service
 * @package csw
 * @author lagarde pierre
 * @copyright BRGM
 * @name cswClient
 * @version 1.0.0
 */
class cswGeoClient {
    private $_cswAddress;
    private $_authentAddress;
    private $_cswLogin;
    private $_cswPassword;
    private $_bAuthent;
    private $_sessionID;

   
    private $_response;

    /**
     *
     * @param String $cswAddress address of the CSW URL
     * @param String $cswLogin login of the user to CSW-T
     * @param String $cswPassword  password of the user to CSW-T
     * @param String $authentAddress address of the login/logout address
     */
    
    function  __construct($cswAddress,$cswLogin=null,$cswPassword=null,$authentAddress=null) {
        $this->_cswAddress=$cswAddress;
        $this->_bAuthent=false;
        if (isset($cswLogin)) {
            $this->_cswLogin=$cswLogin;
            $this->_cswPassword=$cswPassword;
            $this->_authentAddress=$authentAddress;
            $this->_bAuthent=true;
        }
    }

    /**
     *
     * @return bool Request success / error
     */
    private function _callHTTPCSW($request) {

        try {
            $resp= $request->send();
            if (200 == $resp->getStatus()) {
              $this->_response = $resp->getBody();
              $cookies = $resp->getCookies();
              foreach ($cookies as $cook) {                  
                  if ($cook['name']=='JSESSIONID') $this->_sessionID = $cook['value'];
              }
              return true;
            } else {
                $this->_response = $resp->getStatus() . ' ' .$resp->getReasonPhrase();
                return false;
            }
        } catch (HTTP_Request2_Exception $e) {
                $this->_response = 'Error: ' . $e->getMessage();
                return false;
        }

    }

    /**
     *
     * @return bool authentication success or error
     */
    private function _authentication($request) {
        //only available for Geosource and Geonetwork
        //start by logout
        if ($this->_bAuthent) {
            $req = new HTTP_Request2($this->_authentAddress.'/xml.user.logout', HTTP_Request2::METHOD_POST);

            if ($this->_callHTTPCSW($req)) {
                //success so next step
                //start to login
                $req = new HTTP_Request2( $this->_authentAddress.'/xml.user.login');
                $req->setMethod(HTTP_Request2::METHOD_POST)
                        ->setHeader("'Content-type': 'application/x-www-form-urlencoded', 'Accept': 'text/plain'")
                        ->addPostParameter('username', $this->_cswLogin)
                        ->addPostParameter('password', $this->_cswPassword);
                if ($this->_callHTTPCSW($req)) {
                    $request->addCookie('JSESSIONID', $this->_sessionID);
                    return true;
                }
            }
            return false;
        }
        return true;
    }

    /**
     * retrieve a specific metadata with UUID in GeoNetwork / Geosource
     * @param String $id of the metadata
     * @return XML content
     */
    public function getRecordById($id) {
        $getRecodByIDRequest = new HTTP_Request2($this->_cswAddress);
        $getRecodByIDRequest->setMethod(HTTP_Request2::METHOD_POST)
              ->setHeader('Content-type: text/xml; charset=utf-8')
              ->setBody("<?xml version='1.0'?>".
                           "<csw:GetRecordById xmlns:csw='http://www.opengis.net/cat/csw/2.0.2' service='CSW' version='2.0.2' outputSchema='http://www.isotc211.org/2005/gmd'>".
                           "<csw:ElementSetName>full</csw:ElementSetName>".
                           "<csw:Id>".$id."</csw:Id>".
                           "</csw:GetRecordById>");
            //authentication if needed
        if (!$this->_authentication($getRecodByIDRequest)) throw new Exception($this->_response, "001");
        if ($this->_callHTTPCSW($getRecodByIDRequest)) {
                $getRecodByIDRequest=null;
                return $this->_response;
        }
        else {
            $getRecodByIDRequest=null;
            throw new Exception($this->_response, "002");
        }
        
    }
    
        /**
     * retrieve a specific metadata with UUID in GeoNetwork / Geosource
     * @param String $id of the metadata
     * @return XML content
     */
    public function getRecordsWithBBOX($propertyName, $literal, $xmin, $ymin, $xmax, $ymax, $returnable, $nb_records) {
        $getRecodsRequest = new HTTP_Request2($this->_cswAddress);
        $getRecodsRequest->setMethod(HTTP_Request2::METHOD_POST)
          ->setHeader('Content-type: text/xml; charset=utf-8')
		      ->setBody("<?xml version='1.0'?>".
                           "<csw:GetRecords xmlns:csw='http://www.opengis.net/cat/csw/2.0.2' service='CSW' resultType='results' maxRecords='" . $nb_records . "' version='2.0.2'>".
                           "<csw:Query typeNames='csw:Record'>".
                           "<csw:ElementSetName typeNames='csw:Record'>" . $returnable . "</csw:ElementSetName>".
                           "<csw:Constraint version='1.1.0'>".
                           "<Filter xmlns='http://www.opengis.net/ogc' xmlns:gml='http://www.opengis.net/gml'>".
                           "<And>".
                           "<PropertyIsLike wildCard='%' singleChar='_' escape='\\'>".
                           "<PropertyName>" . $propertyName . "</PropertyName>".
                           "<Literal>" . $literal . "</Literal>".
                           "</PropertyIsLike>".
                           "<Intersects><PropertyName>BoundingBox</PropertyName>".
                           "<Envelope><lowerCorner>" . $xmax . " " . $ymin ."</lowerCorner>".
                           "<upperCorner>" . $xmin . " " . $ymax . "</upperCorner>".
                           "</Envelope></Intersects>".
                           "</And>".
                           "</Filter></csw:Constraint></csw:Query></csw:GetRecords>");
            //authentication if needed
            //
			
        if (!$this->_authentication($getRecodsRequest)) throw new Exception($this->_response, "001");
        if ($this->_callHTTPCSW($getRecodsRequest)) {
                $getRecodsRequest=null;
                return $this->_response;
        }
        else {
            $getRecodsRequest=null;
            throw new Exception($this->_response, "002");
        }
    }
    
    public function getRecords($propertyName, $literal, $returnable, $nb_records) {
        $getRecodsRequest = new HTTP_Request2($this->_cswAddress);
        $getRecodsRequest->setMethod(HTTP_Request2::METHOD_POST)
          ->setHeader('Content-type: text/xml; charset=utf-8')
		      ->setBody("<?xml version='1.0'?>".
                           "<csw:GetRecords xmlns:csw='http://www.opengis.net/cat/csw/2.0.2' service='CSW' resultType='results' maxRecords='" . $nb_records . "' version='2.0.2'>".
                           "<csw:Query typeNames='csw:Record'>".
                           "<csw:ElementSetName typeNames='csw:Record'>" . $returnable . "</csw:ElementSetName>".
                           "<csw:Constraint version='1.1.0'>".
                           "<Filter xmlns='http://www.opengis.net/ogc' xmlns:gml='http://www.opengis.net/gml'>".
                           "<PropertyIsLike wildCard='%' singleChar='_' escape='\\'>".
                           "<PropertyName>" . $propertyName . "</PropertyName>".
                           "<Literal>" . $literal . "</Literal>".
                           "</PropertyIsLike>".
                           "</Filter></csw:Constraint></csw:Query></csw:GetRecords>");
            //authentication if needed
            //
        if (!$this->_authentication($getRecodsRequest)) throw new Exception($this->_response, "001");
        if ($this->_callHTTPCSW($getRecodsRequest)) {
                $getRecodsRequest=null;
                return $this->_response;
        }
        else {
            $getRecodsRequest=null;
            throw new Exception($this->_response, "002");
        }
    }
    
    public function getRecordsByTopic($propertyName, $topic, $query, $xmin, $ymin, $xmax, $ymax, $returnable, $nb_records) {
        $getRecodsRequest = new HTTP_Request2($this->_cswAddress);
        $getRecodsRequest->setMethod(HTTP_Request2::METHOD_POST)
          ->setHeader('Content-type: text/xml; charset=utf-8')
		      ->setBody("<?xml version='1.0'?>".
                           "<csw:GetRecords xmlns:csw='http://www.opengis.net/cat/csw/2.0.2' service='CSW' resultType='results' maxRecords='" . $nb_records . "' version='2.0.2'>".
                           "<csw:Query typeNames='csw:Record'>".
                           "<csw:ElementSetName typeNames='csw:Record'>" . $returnable . "</csw:ElementSetName>".
                           "<csw:Constraint version='1.1.0'>".
                           "<Filter xmlns='http://www.opengis.net/ogc' xmlns:gml='http://www.opengis.net/gml'>".
                           "<And>".
                           "<PropertyIsEqualTo>".
                           "<PropertyName>" . $propertyName . "</PropertyName>".
                           "<Literal>" . $topic . "</Literal>".
                           "</PropertyIsEqualTo>".
                           "<PropertyIsLike wildCard='%' singleChar='_' escape='\\'>".
                           "<PropertyName>AnyText</PropertyName>".
                           "<Literal>" . $query . "</Literal>".
                           "</PropertyIsLike>".
						   "</And>".  
                           "<Intersects><PropertyName>BoundingBox</PropertyName>".
                           "<Envelope><lowerCorner>" . $xmax . " " . $ymin ."</lowerCorner>".
                           "<upperCorner>" . $xmin . " " . $ymax . "</upperCorner>".
                           "</Envelope></Intersects>".
						   
                           "</Filter></csw:Constraint></csw:Query></csw:GetRecords>");
            //authentication if needed
            //
        if (!$this->_authentication($getRecodsRequest)) throw new Exception($this->_response, "001");
        if ($this->_callHTTPCSW($getRecodsRequest)) {
                $getRecodsRequest=null;
                return $this->_response;
        }
        else {
            $getRecodsRequest=null;
            throw new Exception($this->_response, "002");
        }
      }        

/*
  http://localhost:8080/geonetwork/srv/en/csw?request=GetRecords
  http://geocatalog.bibl.ulaval.ca/geonetwork/srv/fr/csw?request=GetRecords&service=CSW&version=2.0.2
  &namespace=xmlns%28csw%3Dhttp%3A%2F%2Fwww.opengis.net%2Fcat%2Fcsw%2F2.0.2%29%2Cxmlns%28gmd%3Dhttp%3A%2F%2Fwww.isotc211.org%2F2005%2Fgmd%29
  &constraint=AnyText+like+%25batiment%25
  &constraintLanguage=CQL_TEXT
  &constraint_language_version=1.1.0
  &typeNames=csw%3ARecord
  http://geocatalog.bibl.ulaval.ca/geonetwork/srv/fr/csw?request=GetRecords&service=CSW&version=2.0.2&namespace=xmlns(csw=http://www.opengis.net/cat/csw/2.0.2),xmlns(gmd=http://www.isotc211.org/2005/gmd)&constraint=AnyText like %africa%&constraintLanguage=CQL_TEXT&constraint_language_version=1.1.0&typeNames=csw:Record
*/
    
    
    /**
     *
     * @return Number of metadata in the csw server
     */
    public function getCountRecords() {
        $getCountRecordsRequest = new HTTP_Request2($this->_cswAddress);
        $getCountRecordsRequest->setMethod(HTTP_Request2::METHOD_POST)
                      ->setHeader('Content-type: text/xml; charset=utf-8')
		      ->setBody("<?xml version='1.0'?>".
                                "<csw:GetRecords xmlns:csw='http://www.opengis.net/cat/csw/2.0.2' service='CSW' version='2.0.2' resultType='hits'>".
                                "<csw:Query typeNames='csw:Record'>".
                                "<csw:Constraint version='1.1.0'>".
                                "    <Filter xmlns='http://www.opengis.net/ogc' xmlns:gml='http://www.opengis.net/gml'/>".
                                "</csw:Constraint>".
                                "</csw:Query>".
                                "</csw:GetRecords>");
            //authentication if needed
        if (!$this->_authentication($getCountRecordsRequest)) throw new Exception($this->_response, "001");
        if ($this->_callHTTPCSW($getCountRecordsRequest)) {
                $docXml= new DOMDocument();
                if ($docXml->loadXML($this->_response)) {                    
                    $xp = new DOMXPath($docXml);
                    $xpathString="//@numberOfRecordsMatched";
                    $nodes = $xp->query($xpathString);
                    if ($nodes->length==1)
                        return $nodes->item(0)->textContent;
                    else
                        return 0;
                }
                else {
                    throw new Exception($this->_response, "004");
                }
                
        }
        else
            throw new Exception($this->_response, "003");

    }
    /**
     * Insert a new metadata in the csw server
     * @param DOMDocument $xmlISO19139 content to add
     * @return number of insered metadata
     */
    public function insertMetadata($xmlISO19139) {
        $insertMetadataRequest = new HTTP_Request2($this->_cswAddress);
        $insertMetadataRequest->setMethod(HTTP_Request2::METHOD_POST)
                      ->setHeader('Content-type: text/xml; charset=utf-8')
		      ->setBody("<?xml version='1.0'?>".
                           "<csw:Transaction service='CSW' version='2.0.2' xmlns:csw='http://www.opengis.net/cat/csw/2.0.2' xmlns:ogc='http://www.opengis.net/ogc' xmlns:apiso='http://www.opengis.net/cat/csw/apiso/1.0'>".
                           "<csw:Insert>".str_replace('<?xml version="1.0"?>','',$xmlISO19139->saveXML()).
		           "</csw:Insert>".
                           "</csw:Transaction>");
        //authentication is needed !!
        if (!$this->_authentication($insertMetadataRequest)) throw new Exception("authentication mandatory", "001");
        if ($this->_callHTTPCSW($insertMetadataRequest)) {
                $docXml= new DOMDocument();
                if ($docXml->loadXML($this->_response)) {
                    $xp = new DOMXPath($docXml);
                    $xpathString="//csw:totalInserted";
                    $nodes = $xp->query($xpathString);
                    if ($nodes->length==1)
                        return $nodes->item(0)->textContent;
                    else
                        return 0;
                }
                else {
                    throw new Exception($this->_response, "004");
                }
        }
        else
            throw new Exception($this->_response, "002");


    }

    /**
     * update a  metadata in the csw server
     * @param DOMDocument $xmlISO19139 content to add
     * @return number of updated metadata
     */
    public function updateMetadata($xmlISO19139) {
        
        //first, find the uuid of the metadata !
        
        $nFI=$xmlISO19139->getElementsByTagName('fileIdentifier');
        if ($nFI->length==1) {               
                $uuid = $nFI->item(0)->childNodes->item(1)->nodeValue;
        }
        else
            throw new Exception("No fileIdentifier found","UM.001");
       
        $updateMetadataRequest = new HTTP_Request2($this->_cswAddress);
        $updateMetadataRequest->setMethod(HTTP_Request2::METHOD_POST)
                      ->setHeader('Content-type: text/xml; charset=utf-8')
		      ->setBody("<?xml version='1.0'?>".
                           "<csw:Transaction service='CSW' version='2.0.2' xmlns:csw='http://www.opengis.net/cat/csw/2.0.2' xmlns:ogc='http://www.opengis.net/ogc' xmlns:apiso='http://www.opengis.net/cat/csw/apiso/1.0'>".
                           "<csw:Update>".str_replace('<?xml version="1.0"?>','',$xmlISO19139->saveXML()).
                           "<csw:Constraint version='1.0.0'>".
                           "<Filter xmlns='http://www.opengis.net/ogc' xmlns:gml='http://www.opengis.net/gml'>".
                           "<PropertyIsLike wildCard='%' singleChar='_' escapeChar='\'>".
                           "    <PropertyName>apiso:identifier</PropertyName>".
                           "    <Literal>".$uuid."</Literal>".
                           "</PropertyIsLike>".
                           "</Filter>".
                           "</csw:Constraint>".
		           "</csw:Update>".
                           "</csw:Transaction>");
        //authentication is needed !!
        
        if (!$this->_authentication($updateMetadataRequest)) throw new Exception("authentication mandatory", "001");
        
        if ($this->_callHTTPCSW($updateMetadataRequest)) {
                $docXml= new DOMDocument();
               
                if ($docXml->loadXML($this->_response)) {
                    $xp = new DOMXPath($docXml);
                    $xpathString="//csw:totalUpdated";
                    $nodes = $xp->query($xpathString);
                    if ($nodes->length==1)
                        return $nodes->item(0)->textContent;
                    else
                        return 0;
                }
                else {
                    throw new Exception($this->_response, "004");
                }
        }
        else
            throw new Exception($this->_response, "002");


    }

    /**
     * deleted a  metadata in the csw server
     * @param DOMDocument $xmlISO19139 content to add
     * @return number of deleted metadata
     */
    public function deleteMetadata($xmlISO19139) {
       //first, find the uuid of the metadata !

        $nFI=$xmlISO19139->getElementsByTagName('fileIdentifier');
        if ($nFI->length==1) {
                $uuid = $nFI->item(0)->childNodes->item(1)->nodeValue;
                return $this->deleteMetadataFromUuid($uuid);
        }
        else
            throw new Exception("No fileIdentifier found","UM.001");

    }

    /**
     * delete a  metadata in the csw server
     * @param String $uuid id of the metadata
     * @return number of deleted metadata
     */
    public function deleteMetadataFromUuid($uuid) {

       
        $deleteMetadataRequest = new HTTP_Request2($this->_cswAddress);
        $deleteMetadataRequest->setMethod(HTTP_Request2::METHOD_POST)
                      ->setHeader('Content-type: text/xml; charset=utf-8')
		      ->setBody("<?xml version='1.0'?>".
                           "<csw:Transaction service='CSW' version='2.0.2' xmlns:csw='http://www.opengis.net/cat/csw/2.0.2' xmlns:ogc='http://www.opengis.net/ogc' xmlns:apiso='http://www.opengis.net/cat/csw/apiso/1.0'>".
                           "<csw:Delete>".
                           "<csw:Constraint version='1.0.0'>".
                           "<Filter xmlns='http://www.opengis.net/ogc' xmlns:gml='http://www.opengis.net/gml'>".
                           "<PropertyIsLike wildCard='%' singleChar='_' escapeChar='\'>".
                           "    <PropertyName>apiso:identifier</PropertyName>".
                           "    <Literal>".$uuid."</Literal>".
                           "</PropertyIsLike>".
                           "</Filter>".
                           "</csw:Constraint>".
		           "</csw:Delete>".
                           "</csw:Transaction>");
        //authentication is needed !!

        if (!$this->_authentication($deleteMetadataRequest)) throw new Exception("authentication mandatory", "001");

        if ($this->_callHTTPCSW($deleteMetadataRequest)) {
                $docXml= new DOMDocument();
                if ($docXml->loadXML($this->_response)) {
                    $xp = new DOMXPath($docXml);
                    $xpathString="//csw:totalDeleted";
                    $nodes = $xp->query($xpathString);
                    if ($nodes->length==1)
                        return $nodes->item(0)->textContent;
                    else
                        return 0;
                }
                else {
                    throw new Exception($this->_response, "004");
                }
        }
        else
            throw new Exception($this->_response, "002");


    }

}

//TEST
function testCswGeoClientClass() {

echo "allo";

    $cswClient = new cswGeoClient('http://geosourcedemo.brgm.fr/geosourcedemo/srv/fr/csw', 'editeur', 'editeur', 'http://geosourcedemo.brgm.fr/geosourcedemo/srv/fr');
try {

    

    $res=$cswClient->getRecordById('5a258fa6-103f-427f-88ab-28036db17c11');

    $xml=new DOMDocument();
    //cswLog::logInfo($res);
    $xml->loadXML($res);
    $md_Medatadata=$xml->getElementsByTagName('MD_Metadata')->item(0);
    $xmldoc=new DOMDocument();
    $xmldoc->loadXML($xml->saveXML($md_Medatadata));
    $cswClient->updateMetadata($xmldoc);
    $cswClient->deleteMetadata($xmldoc);


}
catch (Exception $e) {
     echo $e->getMessage();
}
}

?>
