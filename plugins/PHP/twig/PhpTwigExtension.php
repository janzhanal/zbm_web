<?php
namespace Grav\Plugin;
use Symfony\Component\Yaml\Yaml as Yaml;
use Grav\Common\Cache as Cache;
class PhpTwigExtension extends \Twig_Extension
{
    public function getName()
    {
        return 'PhpTwigExtension';
    }
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('api_racelist', [$this, 'api_racelist']),
            new \Twig_SimpleFunction('phpUploadProgram', [$this, 'phpUploadProgram']),
            new \Twig_SimpleFunction('phpNews', [$this, 'NewsFunction']),
            new \Twig_SimpleFunction('phpEditBliziSe', [$this, 'editBliziSeFunction']),
            new \Twig_SimpleFunction('phpWeeklyProgram', [$this, 'save_program_templates']),
            new \Twig_SimpleFunction('phpFormEditEvent', [$this, 'phpFormEditEvent']),
            new \Twig_SimpleFunction('phpSaveEditedEvent', [$this, 'phpSaveEditedEvent']),
            new \Twig_SimpleFunction('phpSavePolaris', [$this, 'SavePolaris']),
            new \Twig_SimpleFunction('phpDeletePolaris', [$this, 'DeletePolaris']),
            new \Twig_SimpleFunction('phpSavePlan', [$this, 'SavePlan']),
            new \Twig_SimpleFunction('phpSavePlanTemplate', [$this, 'SavePlanTemplate']),   
            new \Twig_SimpleFunction('phpLoginRedirect', [$this, 'LoginRedirect']),
            new \Twig_SimpleFunction('phpTest', [$this, 'Test']),      
            new \Twig_SimpleFunction('phpShiftPlan', [$this, 'ShiftPlan']),        
        
        ];
    }

    function LoginRedirect(){
        // placed in "user/plugins/login/templates/login.html.twig" - {{phpLoginRedirect()}}
        $authenticated = False;
        if($_SESSION["user"]["authenticated"]){
            $authenticated = True;
        }

        if(isset($_SERVER['HTTP_REFERER'])) {
            if(!$authenticated){
                // if user is not logged in, save refferer
                $_SESSION['ref'] = $_SERVER['HTTP_REFERER'];
            }
            else{
                $protocol = isset($_SERVER["HTTPS"]) ? 'https' : 'http';

                $login_path = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/login";
                $refferer_path = $_SERVER['HTTP_REFERER'];

                // referrer is a login page and we successfully logged in -> redirect to last page before login page
                if(isset($_SESSION['ref']) && $login_path == $refferer_path){
                    if($_SESSION['ref'] != $login_path){
                        header('Location: ' . $_SESSION['ref']);
                        exit();
                    }
                }
                else{header('Refresh: 1; url=/');}
            }          
        }
    }

// pomocne fce
    /*************************************************************
    ** projde vsechny prvky array a aplikuje htmlspecialchars() **
    **************************************************************/
    function array_htmlspecialchars(&$array){
        array_walk_recursive($array, function(&$value) {
            htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        });
    }

    /**********************************************
    ** zapise soubor i kdyz chybi v ceste slozka **
    **********************************************/
    function file_force_contents($dir, $contents){
        $parts = explode("/", $dir);
        $file = array_pop($parts);
        $dir = "";

        foreach($parts as $part) {
            if (! is_dir($dir .= "{$part}/")) mkdir($dir);
        }
        //$contents = htmlspecialchars($contents, ENT_NOQUOTES, 'UTF-8');
        return file_put_contents("{$dir}{$file}", $contents);
    }


    /******************************************************
    **      odstihne singlequotes, doublequotes          **
    ** a ze zacatku a konce stringu vsechny bile znakya  **
    ******************************************************/
    function trim_all($str){
        $str = str_replace( "'" , "" , $str );
        $str = str_replace( '"' , "" , $str );
        $str = trim($str);
        $str = htmlspecialchars(html_entity_decode($str, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
        $str = str_replace( "\n" , "<br>" , $str );
        return $str;
    }


    function format_date($date){
        $newdate = date_create_from_format('d.m.Y', $date);
        if($newdate){
            return date_format($newdate, 'Y-m-d');
        }
        else{
            return $date;
        }
    }

    function get_event_template($event){
        if(isset($event)){
            switch ($event) {
                case "Z":
                  $template = "zavod";        break;
                case "M":
                case "T":
                  $template = "trenink";      break;
                case "S":
                  $template = "soustredeni";    break;
                case "TABOR":
                  $template = "tabor";    break;
                default:
                  $template = "akce";
              }
        }
        return $template;
    }

    /**********************************************
    **      fce pro parsovani stranek            **
    **********************************************/
    
    function parse_file_frontmatter_only($path_to_file){
        if(!file_exists($path_to_file)){
            header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
            echo 'Cannot parse file, "'. $path_to_file. '" do not match any file.';
            die();
        }
        $txt_file    = file_get_contents($path_to_file); //nacte soubor
        $rows        = explode("\n", $txt_file); //rozdeli na radky
        array_shift($rows); //odstrani prvni radek souboru obsahujici "---"
        $parsed = "";
        foreach($rows as $row){   //prochazi vsechny radky
            if(trim($row) == "---"){
                break;
            }
            $parsed .= $row . PHP_EOL;
        }
        return $parsed;
    }
    
    function parse_file_content_only($path_to_file){
        if(!file_exists($path_to_file)){
            header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
            echo 'Cannot parse file, "'. $path_to_file. '" do not match any file.';
            die();
        }
        $txt_file    = file_get_contents($path_to_file); //nacte soubor
        $rows        = explode("\n", $txt_file); //rozdeli na radky
        array_shift($rows); //odstrani prvni radek souboru obsahujici "---"

        $row_is_content = false;
        $parsed = "";
        foreach($rows as $row){   //prochazi vsechny radky
            if($row_is_content){
                $parsed .= $row . PHP_EOL; 
            }
            else{
                if(trim($row) == "---"){
                    $row_is_content = true;
                }
            }           
        }
        return $parsed;
    }
    
    function combine_frontmatter_with_content($frontmatter, $content){
        $page = "---" . PHP_EOL;
        $page .= $frontmatter;
        $page .= "---" . PHP_EOL;
        $page .= $content;
        return $page;
    }

    function get_frontmatter_as_array($path_to_file){
        $frontmatter_yaml = $this->parse_file_frontmatter_only($path_to_file);
        return Yaml::parse($frontmatter_yaml);
        // https://symfony.com/doc/current/components/yaml.html 
    }

      /***********************************************************
      ** funkce, ktera rozparsuje soubor, vraci array s daty    **
      ************************************************************/
      function parse_file($path_to_file){
        if(!file_exists($path_to_file)){
            return false;
        }
        $txt_file    = file_get_contents($path_to_file); //nacte soubor
        $rows        = explode("\n", $txt_file); //rozdeli na radky
        array_shift($rows); //odstrani prvni radek souboru obsahujici "---"
        $parsed["Rankings"] = 0;
        foreach($rows as $row){   //prochazi vsechny radky
            if( trim($row) == "---" ){
              $parsed["Content"] = "";
              continue;
            }
            if(isset($parsed["Content"])){    //pokud jiz existuje nejaky content (v souboru za "---"), tak ..
              $parsed["Content"] = $parsed["Content"] . PHP_EOL . $row;  //jen dopise radek
              continue; // a pokracuje na dalsi radek
            }

            $row_data   = explode(":", $row, 2); //rozdeli radek na cast pred a za ":"

            $row_data[0] = str_replace(" ", "", $row_data[0]); //odstrani mezery na zacatku radku
            $row_data[0] = str_replace("-", "", $row_data[0]); //odstrani "-" na zacatku radku
            switch ($row_data[0]) {  //ukladam do promene podle textu pred ":"
                case "title":
                    $parsed["Name"] = $this->trim_all($row_data[1]);   break;
                case "date":
                    $parsed["Date"] = $this->trim_all($row_data[1]);    break;
                case "id":
                    $parsed["Id"] = $this->trim_all($row_data[1]);    break;
                case "start":
                    $parsed["Date1"] = $this->trim_all($row_data[1]);   break;
                case "end":
                    $parsed["Date2"] = $this->trim_all($row_data[1]);   break;
                case "place":
                    $parsed["Place"] = $this->trim_all($row_data[1]);   break;
                case "gps":
                    $parsed["GPS"] = $this->trim_all($row_data[1]);   break;
                case "meetTime":
                    $parsed["MeetTime"] = $this->trim_all($row_data[1]);   break;
                case "meetPlace":
                    $parsed["MeetPlace"] = $this->trim_all($row_data[1]);   break;
                case "link":
                    $parsed["Link"] = $this->trim_all($row_data[1]);   break;
                case "club":
                    $parsed["Club"] = $this->trim_all($row_data[1]);   break;
                case "eventTypeDescription":
                    $parsed["EventTypeDescription"] = $this->trim_all($row_data[1]);   break;
                case "startTime":
                    $parsed["StartTime"] = $this->trim_all($row_data[1]);   break;
                case "map":
                    $parsed["Map"] = $this->trim_all($row_data[1]);   break;
                case "terrain":
                    $parsed["Terrain"] = $this->trim_all($row_data[1]);   break;
                case "transport":
                    $parsed["Transport"] = $this->trim_all($row_data[1]);   break;
                case "accomodation":
                    $parsed["Accomodation"] = $this->trim_all($row_data[1]);   break;
                case "food":
                    $parsed["Food"] = $this->trim_all($row_data[1]);   break;
                case "leader":
                    $parsed["Leader"] = $this->trim_all($row_data[1]);   break;
                //template
                case "template":
                    $parsed["Template"] = $this->trim_all($row_data[1]);   break;
                //entries
                case "entry1":
                    $parsed["Entry1"] = $this->trim_all($row_data[1]);   break;
                case "entry2":
                    $parsed["Entry2"] = $this->trim_all($row_data[1]);   break;
                case "entry3":
                    $parsed["Entry3"] = $this->trim_all($row_data[1]);   break;
                case "entry4":
                    $parsed["Entry4"] = $this->trim_all($row_data[1]);   break;
                case "entry5":
                    $parsed["Entry5"] = $this->trim_all($row_data[1]);   break;
                //taxonomy
                case "sport":
                    $parsed["Sport"] = $this->trim_all($row_data[1]);   break;
                case "type":
                    $parsed["Type"] = $this->trim_all($row_data[1]);   break;
                case "cancelled":
                    $parsed["Cancelled"] = $this->trim_all($row_data[1]);   break;
                case "doWeOrganize":
                    $parsed["DoWeOrganize"] = $this->trim_all($row_data[1]);   break;
                    //skupina
                case "zabicky":
                    $parsed["Zabicky"] = 1;   break;
                case "pulci1":
                    $parsed["Pulci1"] = 1;   break;
                case "pulci2":
                    $parsed["Pulci2"] = 1;   break;
                case "zaci1":
                    $parsed["Zaci1"] = 1;   break;
                case "zaci2":
                    $parsed["Zaci2"] = 1;   break;
                case "dorost":
                    $parsed["Dorost"] = 1;   break;
                    //rankings
                case "A":
                    $parsed["A"] = true; $parsed["Rankings"] += 1;   break;
                case "B_morava":
                    $parsed["B_morava"] = true; $parsed["Rankings"] += 2;   break;
                case "B_cechy":
                    $parsed["B_cechy"] = true; $parsed["Rankings"] += 4;   break;
                case "oblast":
                    $parsed["oblast"] = true; $parsed["Rankings"] += 8;   break;
                case "mistrovstvi":
                    $parsed["mistrovstvi"] = true; $parsed["Rankings"] += 16;   break;
                case "stafety":
                    $parsed["stafety"] = true; $parsed["Rankings"] += 32;   break;
                case "verejny":
                    $parsed["verejny"] = true; $parsed["Rankings"] += 128;   break;
                case "rank21":
                    $parsed["Rank21"] = $this->trim_all($row_data[1]);   break;
                //note
                case "note":
                    $parsed["Note"] = $this->trim_all($row_data[1]);   break;
                //soustredeni
                case "return":
                    $parsed["Return"] = $this->trim_all($row_data[1]);   break;
                case "price":
                    $parsed["Price"] = $this->trim_all($row_data[1]);   break;
                case "program":
                    $parsed["Program"] = $this->trim_all($row_data[1]);   break;
                case "thingsToTake":
                    $parsed["ThingsToTake"] = $this->trim_all($row_data[1]);   break;
                case "signups":
                    $parsed["Signups"] = $this->trim_all($row_data[1]);   break;
                default: break;
              }


          }

      return $parsed;
      }

      /************************************************************
      ** funkce, ktera nacte data z array a ulozi je jako soubor **
      *************************************************************/
      /*
      ---
      title: 'Štěpánský běh'
      date: '2018-08-30'
      template: trenink
      id: 'Race_2'
      start: '2018-12-26'
      end: '2018-12-26'
      place: 'Radostice2'
      gps
      meetTime
      meetPlace
      link:
      club: BBM
      eventTypeDescription
      startTime
      map
      terrain
      transport: 'No'
      accomodation: 'No':
      food
      leader
      trainingCamp: 
          - return
          - price
          - progem
          - thingsToTake
          - signups
      entry:
          - entry1: '2018-12-19'
          - entry2:
          - entry3:
          - entry4:
          - entry5:
      taxonomy:
          - sport: jine
          - type: T
          - cancelled: 1
          - skupina: ['pulci1', pulci2, 'zabicky', 'Zaci1', Zaci2, 'dorost']
          - rankings: ['celostatni', 'A', 'B_cechy', 'B_morava', 'oblastni', 'verejny', 'mistrovstvi', 'stafety']
          =doWeOrganize

      note:
      ---
      */
      function generate_content($race){
            $content = "";
            // zapise uvod pro ligu škol popř. pořádáme
            if(!empty($race["Type"])){
                if($race["Type"]=="L"){
                    $content .= "**Pořádáme!!** Předem díky moc za pomoc s pořádáním. Kdo má čas nebo by chtěl
                    omluvit ze školy, hlaste se Zhustovi." . PHP_EOL;
                }
                else{
                    if(!empty($race["DoWeOrganize"])){
                        if($race["DoWeOrganize"]=="1"){
                            $content .= "**Pořádáme!!**" . PHP_EOL;
                        }
                    }
                }
            }
            if(!empty($race["Note"]))                {$content .= "{{page.header.note}}" . PHP_EOL;}
            // jeden řádek s časem, místem srazu a typem dopravy
            if(!empty($race["MeetTime"]))            {$content .= "* **sraz**: {{page.header.meetTime}}"; $writw_eol=true;}
            if(!empty($race["MeetPlace"]))           {$content .= " {{page.header.meetPlace}}."; $writw_eol=true;}
            if(!empty($race["Transport"]))           {$content .= " Doprava {{page.header.transport}}."; $writw_eol=true;}
            if(isset($writw_eol)) {if($writw_eol==true) $content .= PHP_EOL;}

            if(!empty($race["Accomodation"]))        {$content .= "* **ubytování**: {{page.header.accomodation}}" . PHP_EOL;}
            if(!empty($race["Food"]))                {$content .= "* **strava**: {{page.header.food}}" . PHP_EOL;}
            if(!empty($race["StartTime"]))           {$content .= "* **start**: {{page.header.startTime}}" . PHP_EOL;}
            if(!empty($race["Map"]))                 {$content .= "* **mapa**: {{page.header.map}}" . PHP_EOL;}
            if(!empty($race["Terrain"]))             {$content .= "* **terén**: {{page.header.terrain}}" . PHP_EOL;}
            return $content;
      }

      function array_to_file($race, $template, $path){ //$race je array s daty urciteho zavodu a $id jeho data_id
        //nactene informace se postupne ukladaji do $event, nektere jsou zapsany jen kdyz jsou zadany
        
        $event =
        //base
          "---" . PHP_EOL .
          "title: '" . $race["Name"] . "'" . PHP_EOL .
          "date: '" . date("Y-m-d") . "'" . PHP_EOL .
          "id: '" . $race["Id"] . "'" . PHP_EOL .
          "start: '" . $this->format_date($race["Date1"]) . "'" . PHP_EOL .
          "end: '" . (isset($race["Date2"]) ? $this->format_date($race["Date2"]) : $this->format_date($race["Date1"])) . "'" . PHP_EOL;
        //non-necesary info
        if(isset($race["Place"]))               $event .= "place: '" . $race["Place"] . "'" . PHP_EOL;
        if(isset($race["GPS"]))                 $event .= "gps: '" . $race["GPS"] . "'" . PHP_EOL;
        if(isset($race["MeetTime"]))            $event .= "meetTime: '" . $race["MeetTime"] . "'" . PHP_EOL;
        if(isset($race["MeetPlace"]))           $event .= "meetPlace: '" . $race["MeetPlace"] . "'" . PHP_EOL;
        if(isset($race["Link"]))                $event .= "link: '" . $race["Link"]. "'"  . PHP_EOL;
        if(isset($race["Club"]))                $event .= "club: '" . $race["Club"]. "'"  . PHP_EOL;
        if(isset($race["EventTypeDescription"]))$event .= "eventTypeDescription: '" . $race["EventTypeDescription"] . "'" . PHP_EOL;
        if(isset($race["StartTime"]))               $event .= "startTime: '" . $race["StartTime"] . "'" . PHP_EOL;
        if(isset($race["Map"]))                 $event .= "map: '" . $race["Map"] . "'" . PHP_EOL;
        if(isset($race["Terrain"]))             $event .= "terrain: '" . $race["Terrain"] . "'" . PHP_EOL;
        if(isset($race["Transport"]))           $event .= "transport: '" . $race["Transport"] . "'". PHP_EOL;
        if(isset($race["Accomodation"]))        $event .= "accomodation: '" . $race["Accomodation"] . "'". PHP_EOL;
        if(isset($race["Food"]))                $event .= "food: '" . $race["Food"]. "'"  . PHP_EOL;
        if(isset($race["Leader"]))              $event .= "leader: '" . $race["Leader"] . "'" . PHP_EOL;
        if(isset($race["Sport"]))               $event .= "sport: '" . $race["Sport"]. "'"  . PHP_EOL;
        if(isset($race["Type"]))                $event .= "type: '" . $race["Type"]. "'"  . PHP_EOL;
        if(isset($race["Cancelled"]))           $event .= "cancelled: '" . $race["Cancelled"]. "'"  . PHP_EOL;
        if(isset($race["DoWeOrganize"]))        $event .= "doWeOrganize: '" . $race["DoWeOrganize"]. "'"  . PHP_EOL;
        //soustredeni
        if(isset($race["Program"])){
                                                $event .= "trainingCamp:". PHP_EOL;
            if(isset($race["Program"]))         $event .= "    program: '" . $race["Program"] . "'" . PHP_EOL;
            if(isset($race["Price"]))           $event .= "    price: '" . $race["Price"] . "'" . PHP_EOL;
            if(isset($race["ThingsToTake"]))    $event .= "    thingsToTake: '" . $race["ThingsToTake"]  . "'" . PHP_EOL;
            if(isset($race["Return"]))          $event .= "    return: '" . $race["Return"] . "'" . PHP_EOL;
            if(isset($race["Signups"]))         $event .= "    signups: '" . $race["Signups"] . "'" . PHP_EOL;
        }
        //template
        $event .= "template: ".$template. PHP_EOL;

        //entry dates
        if(isset($race["Entry1"])) $event .= "entries:" . PHP_EOL .
                                               "    entry1: '" . $race["Entry1"] . "'". PHP_EOL;
          if(isset($race["Entry2"])) $event .= "    entry2: '" . $race["Entry2"] . "'". PHP_EOL;
          if(isset($race["Entry3"])) $event .= "    entry3: '" . $race["Entry3"] . "'". PHP_EOL;
          if(isset($race["Entry4"])) $event .= "    entry4: '" . $race["Entry4"] . "'". PHP_EOL;
          if(isset($race["Entry5"])) $event .= "    entry5: '" . $race["Entry5"] . "'". PHP_EOL;

        //taxonomy
        $event .= "taxonomy:" . PHP_EOL;
                                            $event .= "    skupina:" . PHP_EOL;
          if(isset($race["Zabicky"])) if($race["Zabicky"] == "1")  $event .= "        - zabicky". PHP_EOL;
          if(isset($race["Pulci1"]))  if($race["Pulci1"]  == "1")  $event .= "        - pulci1" . PHP_EOL;
          if(isset($race["Pulci2"]))  if($race["Pulci2"]  == "1")  $event .= "        - pulci2" . PHP_EOL;
          if(isset($race["Zaci1"]))   if($race["Zaci1"]   == "1")  $event .= "        - zaci1"  . PHP_EOL;
          if(isset($race["Zaci2"]))   if($race["Zaci2"]   == "1")  $event .= "        - zaci2"  . PHP_EOL;
          if(isset($race["Dorost"]))  if($race["Dorost"]  == "1")  $event .= "        - dorost" . PHP_EOL;
        if(isset($race["Rankings"])){
            $event .= "    rankings:" . PHP_EOL;
            $rankings = (int)$race["Rankings"];
            if( ($rankings-128) >= 0 ) {
                $event .= "        - verejny" . PHP_EOL;
                $rankings -= 128;
                }
                if( ($rankings-32) >= 0 ) {
                    $event .= "        - stafety" . PHP_EOL;
                    $rankings -= 32;
                }
                if( ($rankings-16) >= 0 ) {
                    $event .= "        - mistrovstvi" . PHP_EOL;
                    $rankings -= 16;
                    }
                    if( ($rankings-8) >= 0 ) {
                        $event .= "        - oblast" . PHP_EOL;
                        $rankings -= 8;
                    }
                    if( ($rankings-4) >= 0 ) {
                        $event .= "        - B_cechy" . PHP_EOL;
                        $rankings -= 4;
                        }
                        if( ($rankings-2) >= 0 ) {
                            $event .= "        - B_morava" . PHP_EOL;
                            $rankings -= 2;
                        }
                        if( ($rankings-1) >= 0 ) {
                            $event .= "        - A" . PHP_EOL;
                            $rankings -= 1;
                            }
        }
        if(isset($race["Rank21"]))  $event .=  "        - rank21: " . $race["Rank21"] . PHP_EOL;
        if(isset($race["Note"]))    $event .=  "note: '" . $race["Note"]. "'"  . PHP_EOL;
        $event .=  "---" . PHP_EOL;
        
        if(isset($race["Content"])){
            $race["Content"] = htmlspecialchars($race["Content"], ENT_NOQUOTES, 'UTF-8');
            $event .= $race["Content"] . PHP_EOL;
        }
        else{ 
            $event .= $this->generate_content($race);
        } 
        $this->file_force_contents($path, $event);
      }



      public function ApiRegisterDeadlines(){}

      /*******************************************************
      **  funkce, ktera nacte JSON data z api, pokud zavod  **
      **  neexistuje, je vytvoren, pokud existuje, jsou     **
      **  rozdilna data aktualizovana z clenske sekce       **
      ********************************************************

      function api_racelist(){

        $api = file_get_contents("https://members.eob.cz/tst/api_racelist.php"); //nahraje JSON do stringu
        $json = json_decode($api, true, JSON_UNESCAPED_UNICODE); //a dekoduje ho vcetne UTF8 znaků

        if($json["Status"]=="OK"){ //pokud byl dotaz na api uspesny
          foreach ($json["Data"] as $id => $data_json) { // prochazi kazdou akci
            // pokud jiz soubor existuje, aktualizuje rozdilna data
            if( file_exists("./user/pages/databaze/akce/". $id) ) {
              $parsed_file = $this->parse_file("./user/pages/databaze/akce/". $id . "/default.cs.md");   //rozparsuje existujici soubor
              $changed = false;
              foreach ($data_json as $data_id => $json_value) {   //projde data podle id v JSONu
                if(isset($parsed_file[$data_id])){ //pokud existuje zaznam dat z JSONu v souboru
                  if($parsed_file[$data_id] != $json_value) { //porovnaji se zaznamy, pokud jsou rozdilna data
                    $parsed_file[$data_id] = $json_value;  //zapise se znmena
                    $changed = true; //zaznamena se zmena

                  }
                }
                else{ //pokud neexistuje zaznam dat z JSONu v souboru
                  $parsed_file[$data_id] = $json_value; //data se zapisou
                  $changed = true; //zaznamena se zmena


                }
              }
              if($changed == true){ //pokud se neco zmenilo
                $this->array_to_file($parsed_file, $id); // ulozi
              }
            }
            // pokud soubor s eventem neexistuje, je vytvoren
            else{
              $this->array_to_file($data_json, $id);
            }

          }
        }
        else{ mail("otakar.hirs@egmail.com","JSON error zbmob.cz", "Nacteni dat z clenske sekce pres JSON neskoncilo OK, mel bys to asi zkontrolovat. \n Ota - 2018"); }

      }*/




//******************************************************************************************************/
//pridat nebo upravit Novinku



/************************************************************
** funkce, ktera ulozi novinku jako soubor **
*************************************************************/

    function news_to_file($data, $year){
      $news = "---" . PHP_EOL .
              "title: '" . $data['Title'] . "'" . PHP_EOL .
              "date: '" . $data['Date'] . "'" . PHP_EOL .
              "template: novinka" . PHP_EOL .
              "id: '" . $data['Id'] . "'" . PHP_EOL .
              "user: '" . $data['User'] . "'" . PHP_EOL .
              "pictures:" . PHP_EOL;
              if(isset($data['dropzone_files'])){
                foreach ($data['dropzone_files'] as $img) {
                  $news .=  "    - name: '" . $data['TimeStamp'] . "_" . $img . "'" . PHP_EOL .
                            "      ratio: '1/4'" . PHP_EOL;
                }
              }
              if(isset($data['img'])){
                foreach ($data['img'] as $img) {
                  if(isset($img['img_delete'])){
                    if($img['img_delete'] == "true"){
                      unlink("./user/pages/databaze/".$year."/novinky/novinka_". $data['Id'] . "/img/" . $img['img_name']);
                      unlink("./user/pages/databaze/".$year."/novinky/novinka_". $data['Id'] . "/img/" . "preview_" . $img['img_name']);
                      continue;
                    }
                  }
                  $news .=  "    - name: '" . $img['img_name'] . "'" . PHP_EOL .
                            "      ratio: '". $img['img_ratio'] ."'" . PHP_EOL;
                }
              }
     $news .= "---" . PHP_EOL .
              $data['Content'] . PHP_EOL;

        $news = htmlspecialchars($news, ENT_NOQUOTES, 'UTF-8');
        //probehne vytvoreni slozky a ulozeni souboru
        $this->file_force_contents("./user/pages/databaze/".$year."/novinky/novinka_". $data['Id'] . "/default.cs.md", $news);
    }

    /******************************************************
     **********   Create thumbnail          **************
    ***************************************************** */
    // Link image type to correct image loader and saver
    // - makes it easier to add additional types later on
    // - makes the function easier to read
    const IMAGE_HANDLERS = [
        IMAGETYPE_JPEG => [
            'load' => 'imagecreatefromjpeg',
            'save' => 'imagejpeg',
            'quality' => 70
        ],
        IMAGETYPE_PNG => [
            'load' => 'imagecreatefrompng',
            'save' => 'imagepng',
            'quality' => 4
        ],
        IMAGETYPE_GIF => [
            'load' => 'imagecreatefromgif',
            'save' => 'imagegif'
        ]
    ];

    /**
     * @param $src - a valid file location
     * @param $dest - a valid file target
     * @param $targetWidth - desired output width
     * @param $targetHeight - desired output height or null
     */
    function createThumbnail($src, $dest, $targetWidth, $targetHeight = null) {

        // 1. Load the image from the given $src
        // - see if the file actually exists
        // - check if it's of a valid image type
        // - load the image resource

        // get the type of the image
        // we need the type to determine the correct loader
        $type = exif_imagetype($src);

        // if no valid type or no handler found -> exit
        if (!$type || !self::IMAGE_HANDLERS[$type]) {
            return null;
        }

        // load the image with the correct loader
        $image = call_user_func(self::IMAGE_HANDLERS[$type]['load'], $src);

        // no image found at supplied location -> exit
        if (!$image) {
            return null;
        }


        // 2. Create a thumbnail and resize the loaded $image
        // - get the image dimensions
        // - define the output size appropriately
        // - create a thumbnail based on that size
        // - set alpha transparency for GIFs and PNGs
        // - draw the final thumbnail

        // get original image width and height
        $width = imagesx($image);
        $height = imagesy($image);

        // maintain aspect ratio when no height set
        if ($targetHeight == null) {

            // get width to height ratio
            $ratio = $width / $height;

            // if is portrait
            // use ratio to scale height to fit in square
            if ($width > $height) {
                $targetHeight = floor($targetWidth / $ratio);
            }
            // if is landscape
            // use ratio to scale width to fit in square
            else {
                $targetHeight = $targetWidth;
                $targetWidth = floor($targetWidth * $ratio);
            }
        }

        // create duplicate image based on calculated target size
        $thumbnail = imagecreatetruecolor($targetWidth, $targetHeight);

        // set transparency options for GIFs and PNGs
        if ($type == IMAGETYPE_GIF || $type == IMAGETYPE_PNG) {

            // make image transparent
            imagecolortransparent(
                $thumbnail,
                imagecolorallocate($thumbnail, 0, 0, 0)
            );

            // additional settings for PNGs
            if ($type == IMAGETYPE_PNG) {
                imagealphablending($thumbnail, false);
                imagesavealpha($thumbnail, true);
            }
        }

        // copy entire source image to duplicate image and resize
        imagecopyresampled(
            $thumbnail,
            $image,
            0, 0, 0, 0,
            $targetWidth, $targetHeight,
            $width, $height
        );


        // 3. Save the $thumbnail to disk
        // - call the correct save method
        // - set the correct quality level

        // save the duplicate version of the image to disk
        if($type == IMAGETYPE_GIF){
            return call_user_func(
                self::IMAGE_HANDLERS[$type]['save'],
                $thumbnail,
                $dest
            );
        }
        
        return call_user_func(
            self::IMAGE_HANDLERS[$type]['save'],
            $thumbnail,
            $dest,
            self::IMAGE_HANDLERS[$type]['quality']
        );
    }


    function process_files($id, $timeStamp, $previewWidthInPx, $year){
    
        $storeFolder = "./user/pages/databaze/".$year."/novinky/novinka_". $id . "/img/";

        $extension=array("jpeg","jpg","png","gif","JPEG","JPG","PNG","GIF","jpe","jif","jfif","jfi","JPE","JIF","JFIF","JFI"); //.jpe .jif, .jfif, .jfi jsou soubory jpeg

        foreach($_FILES["file"]["tmp_name"] as $key=>$tmp_name){
                    $file_name=$_FILES["file"]["name"][$key];
                    $ext=pathinfo($file_name,PATHINFO_EXTENSION);
                    if(!in_array($ext,$extension))
                    {
                        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
                        echo "<em>" . $file_name . "</em>není podporovaný typ obrázku";
                        die();
                    }
                
        }

        foreach($_FILES["file"]["tmp_name"] as $key=>$tmp_name){

                    $file_name=$_FILES["file"]["name"][$key];
                    $file_tmp=$_FILES["file"]["tmp_name"][$key];
                
                    if(!file_exists($storeFolder . $file_name)){
                        if(! is_dir($storeFolder)){
                            mkdir($storeFolder);
                        }
                        $saveImagePath = $storeFolder . $timeStamp . "_" . $file_name;
                        $savePreviewPath = $storeFolder . "preview_" . $timeStamp . "_" . $file_name;
                        move_uploaded_file($file_tmp=$_FILES["file"]["tmp_name"][$key], $saveImagePath);
                        $this->createThumbnail($saveImagePath, $savePreviewPath, $previewWidthInPx, $targetHeight = null);
                    };
        }
    }

    function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (filetype($dir."/".$object) == "dir") $this->rrmdir($dir."/".$object); else unlink($dir."/".$object);
            }
            }
            reset($objects);
            rmdir($dir);
        }
    }

    function save_news($user, $id, $date, $year){
        $data['TimeStamp'] = time();
        $data['Title'] = $_POST["title"];
        $data['Id'] = $id;
        $data['User'] = $user;
        $data['Date'] = $date;
        $data['Content'] = $_POST['content'];
        if(isset($_POST['img'])){
        $data['img'] = $_POST['img'];
        }
        if(isset($_POST['dropzone_files'])){
        $data['dropzone_files'] = $_POST['dropzone_files'];
        $this->news_to_file($data, $year);
        $this->process_files($data['Id'], $data['TimeStamp'], 1000, $year);
        }
        else {
        $this->news_to_file($data, $year);
        }
    }

    public function NewsFunction($user){
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            if(isset($_POST["POST_type"])){
                if( $_POST["POST_type"] == "addNews" ){
                    $id = date("Ymd-Hisv");
                    $date = date("Y-m-d");
                    $year = substr($date, 0 , 4);
                    $this->save_news($user, $id, $date, $year);
                }
                elseif( $_POST["POST_type"] == "updateNews" ){
                    $id = $_POST["id"];
                    $date = date( "Y-m-d", strtotime(str_replace(' ','', $_POST["date"])) );
                    $year = substr($date, 0 , 4);
                    $this->save_news($user, $id, $date, $year);
                }
                elseif( $_POST["POST_type"] == "deleteNews" ){
                    $year = substr($_POST["id"], 0 , 4);
                    $this->rrmdir("./user/pages/databaze/".$year."/novinky/novinka_". $_POST['id'] . "/");
                }
                Cache::clearCache('all');
            }
        }
    }

    //******************************************************************************************************/
    //updatuje kontent zobrazovany v Blizi se
    public function editBliziSeFunction($user){
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            if(isset($_POST["POST_type"])){
                if( $_POST["POST_type"] == "editBliziSe" ){
                    $year = substr($_POST["id"], 1, 4);
                    $template = $_POST['template'];
                    $path = "./user/pages/databaze/". $year ."/". $template ."/". $_POST["id"] . "/".$template.".cs.md";
                    $parsed_file = $this->parse_file($path);   //rozparsuje existujici soubor

                    if(isset($_POST["regenerate"])){
                        if($_POST["regenerate"]){
                            $parsed_file["Content"] = $this->generate_content($parsed_file);
                        }   
                    }
                    else{
                        $parsed_file["Content"] = $_POST["content"];
                    }

                    $this->array_to_file($parsed_file, $template, $path);
                    Cache::clearCache('all');
                    //echo"<script type='text/javascript'>window.location.replace(location.href);</script>";
                }
            }
        }
    }

    /********************************************************************************
    *********************  plan pravidelnych treninku *******************************
    ********************************************************************************/
    

    function get_plan_template($path_to_file){
        $frontmatter = $this->get_frontmatter_as_array($path_to_file);
        $template = $frontmatter["planTemplate"];
        return $template;
    }

    function add_season_to_string($season,$data){
        $week = ["monday","tuesday","wednesday","thursday","friday","saturday", "sunday"];
        $last_day_printed = 0;
        foreach ($_POST[$season] as $day_num => $day){
            while($day_num > $last_day_printed){
                $data .= "    ".$week[$last_day_printed].": null". PHP_EOL;
                $last_day_printed++;
            }
            $last_day_printed++;
            $data .= "    ".$week[$day_num].":". PHP_EOL;
            $i = 1;
            foreach ($day as $event){
                $data .= "        " . $i .":" . PHP_EOL .
                        "            name: '"  . $event["name"] ."'". PHP_EOL .
                        "            place: '"  . $event["place"] ."'". PHP_EOL .
                        "            meetup: '"  . $event["meetup"] ."'". PHP_EOL .
                        "            group:". PHP_EOL;
                if(!empty($event["group"])) {
                foreach($event["group"] as $group){
                    $data .= "                    - " . $group . PHP_EOL;
                }
                }
                $i++;
            }
        }
        while($last_day_printed < 7){
            $data .= "    ".$week[$last_day_printed].": null". PHP_EOL;
            $last_day_printed++;
        }
        return $data;
    }

    // ulozit sablony
    function save_program_templates(){
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            if(isset($_POST["POST_type"])){
                if( $_POST["POST_type"] == "weeklyPlan" ){
                    $data = "---" . PHP_EOL .
                            "title: 'Týdenní program'" . PHP_EOL .
                            "date: '2018-09-30'" . PHP_EOL .
                            "process:". PHP_EOL .
                            "    twig: true" . PHP_EOL .
                            "    markdown: false" . PHP_EOL .
                            "access:" . PHP_EOL .
                            "    site:" . PHP_EOL .
                            "        plan: true" . PHP_EOL .
                            "currentSeason: " . $_POST["season"]  . PHP_EOL .
                            "summer:" . PHP_EOL;
                    if(isset($_POST['summer'])){
                        $data = $this->add_season_to_string("summer",$data);
                    }
                    $data .= "winter:" . PHP_EOL;
                    if(isset($_POST['winter'])){
                        $data = $this->add_season_to_string("winter",$data);
                    }
                    $data .= "---" . PHP_EOL;
                    $data .= $this->parse_file_content_only($_POST["filePath"]);

                    $this->file_force_contents($_POST["filePath"], $data);
                    Cache::clearCache('all');
                }
            }
        }
    }
/********************************************************
***** tento tyden, pristi tyden / plan, plan-next *******
*********************************************************/
    // ulozit plan
    function SavePlan(){
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $data = "---" . PHP_EOL .
                    "process:". PHP_EOL .
                    "    twig: true" . PHP_EOL .
                    "    markdown: false" . PHP_EOL .
                    "access:" . PHP_EOL .
                    "    site:" . PHP_EOL .
                    "        plan: true" . PHP_EOL .
                    "planTemplate: " . $_POST["template"] . PHP_EOL .
                    "plan:" . PHP_EOL;
            if(isset($_POST['data'])){
                $data = $this->add_season_to_string("data",$data);
            }
            $data .= "---" . PHP_EOL;
            $data .= $this->parse_file_content_only($_POST["filePath"]);

            $this->file_force_contents($_POST["filePath"], $data);
            Cache::clearCache('all');
        }
    }

    function get_plan_from_template($template){
        if($template == "None"){
            return;
        }

        $templates_path = "./user/pages/auth/plan-templates/default--plan-header.cs.md";
        $frontmatter = $this->get_frontmatter_as_array($templates_path);

        // retun plan as array
        return $frontmatter[$template]; 
    }

    // nacist sablonu
    function SavePlanTemplate(){
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $page_path = $_POST["filePath"];
            $templates_path = str_replace(array('/plan/', '/plan-next/'), '/plan-templates/', $page_path);
            // get last used teamplates
            $template = $_POST["template"];

            // alternate frontmatter
            $frontmatter = $this->get_frontmatter_as_array($page_path);             
            $frontmatter['planTemplate'] = $template;                               // set last used template to the chosen one
            $frontmatter['plan'] = $this->get_plan_from_template($template);        // get chosen plan from page plan-templates
            $frontmatter = Yaml::dump($frontmatter, 10);                            // make string from array 

            // get page content
            $content = $this->parse_file_content_only($page_path);

            // build page
            $page = $this->combine_frontmatter_with_content($frontmatter, $content);
           
            // save page
            $this->file_force_contents($page_path, $page);
            Cache::clearCache('all');
        }
    }

    // nastavi "pristi tyden" jako "tento tyden" a do "pristi tyden" nacte predchozi pouzitou sablonu - potreba CRON/ task scheduler 
    function ShiftPlan($plan_path, $plan_next_path){
            /******************/
            // update this week
            /******************/
            $frontmatter = $this->parse_file_frontmatter_only($plan_next_path);
            $content = $this->parse_file_content_only($plan_path);
            $page = $this->combine_frontmatter_with_content($frontmatter, $content);

            $this->file_force_contents($plan_path, $page);

            /******************************/
            // load template for next week 
            /******************************/
            $template = $this->get_plan_template($plan_next_path);
            // alternate frontmatter
            $frontmatter = $this->get_frontmatter_as_array($plan_next_path);             
            $frontmatter['planTemplate'] = $template;                               // set last used template to the chosen one
            $frontmatter['plan'] = $this->get_plan_from_template($template);        // get chosen plan from page plan-templates
            $frontmatter = Yaml::dump($frontmatter, 10);                            // make string from array 
            // get page content
            $content = $this->parse_file_content_only($page_path);
            // build page
            $page = $this->combine_frontmatter_with_content($frontmatter, $content); 
            // save page
            $this->file_force_contents($page_path, $page);

            Cache::clearCache('all');        
    }




//******************************************************************************************************/
    //nahravani programu z CSV souboru
    function parse_csv(){
        $file_name = $_FILES['csv']['tmp_name'];
        $extension = pathinfo($_FILES['csv']['name'], PATHINFO_EXTENSION);
        if ($extension != "csv"){ //pokud soubor neni csv vrati error
          http_response_code(415);
          die();
        }
        $csv_string = file_get_contents($file_name);
        $csv_string = iconv( "Windows-1250", "UTF-8", $csv_string);
        $rows = preg_split("/\\r\\n/", $csv_string); //rozdeli csv soubor po radcich
        array_shift($rows); //odstrani prvni radek souboru obsahujici záhlaví tabulky

        //= zahlavi tabulky csv souboru
        $csv_scheme = ["Type", "Date1", "Date2", "Name", "Place", "GPS", "MeetTime", "MeetPlace", "EventTypeDescription", "StartTime", "Zabicky", "Pulci1", "Pulci2", "Zaci1", "Zaci2", "Dorost", "Map", "Terrain", "Transport", "Accomodation", "Food", "Leader", "DoWeOrganize", "Note", "Return", "Price", "Program", "ThingsToTake", "Signups", "Id"];
        $approved_types = ["Z", "M", "T", "S", "BZL", "BBP", "TABOR", "L", "J"]; //ignoruje poznamky

        foreach($rows as $row_num => $row){   //prochazi vsechny radky
            $row_data = explode(";", $row); //rozdeli radek na jednotlive polozky oddelene ";"
            if(isset($row_data[0])){
              if(!in_array(trim($row_data[0]), $approved_types)){ //parsuje jen spravne zaznamy
                continue;
              }
            }
            foreach($csv_scheme as $collum_num => $collum){ //prochazi sloupce a uklada do array
                $parsed[$row_num][$collum]= $this->trim_all($row_data[$collum_num]);
            }
        }
    return $parsed;
    }

    public function phpUploadProgram(){
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            if(isset($_POST["POST_type"])){
                if( $_POST["POST_type"] == "uploadProgram" ){
                    $parsed_csv = $this->parse_csv();

                    foreach($parsed_csv as $csv_event){
                      //var_dump($csv_event);
                        $template = $this->get_event_template($csv_event["Type"]);

                        $year = substr($csv_event["Date1"], -4);
                        $path = "./user/pages/databaze/" . $year ."/".$template."/". $csv_event["Id"] . "/".$template.".cs.md";
                        $existing_file_parsed = $this->parse_file($path);
                        if($existing_file_parsed != false){
                            foreach($csv_event as $attribute){
                                if(!isset($existing_file_parsed[$attribute]) and isset($csv_event[$attribute])){
                                    $existing_file_parsed[$attribute] = $csv_event[$attribute];
                                }
                            }
                            $this->array_to_file($existing_file_parsed, $template, $path);
                        }
                        else{
                            $this->array_to_file($csv_event, $template, $path);
                        }
                        Cache::clearCache('all');
                    }
                }
            }
        }
    }

    /*
     * Convert DMS (degrees / minutes / seconds) to decimal degrees
     *
     * https://github.com/prairiewest/PHPconvertDMSToDecimal
     * 
     * Todd Trann
     * May 22, 2015
     */
    function convertDMSToDecimal($latlng) {
        $valid = false;
        $decimal_degrees = 0;
        $degrees = 0; $minutes = 0; $seconds = 0; $direction = 1;
    
        // Determine if there are extra periods in the input string
        $num_periods = substr_count($latlng, '.');
        if ($num_periods > 1) {
            $temp = preg_replace('/\./', ' ', $latlng, $num_periods - 1); // replace all but last period with delimiter
            $temp = trim(preg_replace('/[a-zA-Z]/','',$temp)); // when counting chunks we only want numbers
            $chunk_count = count(explode(" ",$temp));
            if ($chunk_count > 2) {
                $latlng = preg_replace('/\./', ' ', $latlng, $num_periods - 1); // remove last period
            } else {
                $latlng = str_replace("."," ",$latlng); // remove all periods, not enough chunks left by keeping last one
            }
        }
        
        // Remove unneeded characters
        $latlng = trim($latlng);
        $latlng = str_replace("º"," ",$latlng);
        $latlng = str_replace('°'," ",$latlng);
        $latlng = str_replace("'"," ",$latlng);
        $latlng = str_replace("\""," ",$latlng);
        $latlng = str_replace("  "," ",$latlng);
        $latlng = substr($latlng,0,1) . str_replace('-', ' ', substr($latlng,1)); // remove all but first dash
    
        if ($latlng != "") {
            // DMS with the direction at the start of the string
            if (preg_match("/^([nsewNSEW]?)\s*(\d{1,3})\s+(\d{1,3})\s+(\d+\.?\d*)$/",$latlng,$matches)) {
                $valid = true;
                $degrees = intval($matches[2]);
                $minutes = intval($matches[3]);
                $seconds = floatval($matches[4]);
                if (strtoupper($matches[1]) == "S" || strtoupper($matches[1]) == "W")
                    $direction = -1;
            }
            // DMS with the direction at the end of the string
            elseif (preg_match("/^(-?\d{1,3})\s+(\d{1,3})\s+(\d+(?:\.\d+)?)\s*([nsewNSEW]?)$/",$latlng,$matches)) {
                $valid = true;
                $degrees = intval($matches[1]);
                $minutes = intval($matches[2]);
                $seconds = floatval($matches[3]);
                if (strtoupper($matches[4]) == "S" || strtoupper($matches[4]) == "W" || $degrees < 0) {
                    $direction = -1;
                    $degrees = abs($degrees);
                }
            }
            if ($valid) {
                // A match was found, do the calculation
                $decimal_degrees = ($degrees + ($minutes / 60) + ($seconds / 3600)) * $direction;
            } else {
                // Decimal degrees with a direction at the start of the string
                if (preg_match("/^([nsewNSEW]?)\s*(\d+(?:\.\d+)?)$/",$latlng,$matches)) {
                    $valid = true;
                    if (strtoupper($matches[1]) == "S" || strtoupper($matches[1]) == "W")
                        $direction = -1;
                    $decimal_degrees = $matches[2] * $direction;
                }
                // Decimal degrees with a direction at the end of the string
                elseif (preg_match("/^(-?\d+(?:\.\d+)?)\s*([nsewNSEW]?)$/",$latlng,$matches)) {
                    $valid = true;
                    if (strtoupper($matches[2]) == "S" || strtoupper($matches[2]) == "W" || $degrees < 0) {
                        $direction = -1;
                        $degrees = abs($degrees);
                    }
                    $decimal_degrees = $matches[1] * $direction;
                }
            }
        }
        if ($valid) {
            return substr($decimal_degrees,0,8); // 5 decimal places
        } else {
            return false;
        }
    }

    function normalize_GPS($latlng){
        if(!strpos($latlng, ",")){
            header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
            echo '<br>Nepodporovaný formát GPS: hodnoty zem. šířky a délky musí být odděleny čárkou. <br>např 50°42\'38.9"N<b>,</b> 15°36\'56.6"E';
            die();
        }

        $arr = explode( "," , $latlng );

        $lng = $arr[1]; // Longitude of Brno: 16.606837
        $lat = $arr[0]; // Latitude of Brno: 49.195060
        if($arr)

        $lng = $this->convertDMSToDecimal($lng);
        $lat = $this->convertDMSToDecimal($lat);

        if($lng && $lat){
            return ($lng . ", " . $lat);
        }
        return False;
    }

    // formular pro upravu eventu
    public function phpFormEditEvent(){
        
        if(isset($_GET['event'])){
            $parsed = $this->parse_file("./user/pages".$_GET['event']);

            echo'<form id="editEvent" class="pure-form pure-form-aligned" method="post" action="">
                    <input name="POST_type" type="hidden" value="editEvent">
                    <input name="Id" type="hidden" value="'.($parsed["Id"]??'').'">
                    <input name="Template" id="template" type="hidden" value="'.($parsed["Template"]??'').'">

                    '.($parsed["Id"]??'').'

                    <!--
                    <select name="Template">
                        <option value="akce">Jiné</option>
                        <option value="zavod" '. (isset($parsed["Template"])?($parsed["Template"]=="zavod"?"selected":""):"") .'>Závod</option>
                        <option value="trenink" '. (isset($parsed["Template"])?($parsed["Template"]=="trenink"?"selected":""):"") .'>Trénink</option>
                        <option value="soustredeni" '. (isset($parsed["Template"])?($parsed["Template"]=="soustredeni"?"selected":""):"") .'>Soustředění</option>
                    </select> -->
                    <div class="pure-g">
                      <div class="pure-g">
                          <div class="pure-u-10-24">
                              <div class="pure-g">
                                  <div class="pure-u-1">
                                      <label for="name">Název</label>
                                      <input id="name" name="Name" type="text" value="'.($parsed["Name"]??'').'" required>
                                  </div>
                                  <div class="pure-u-1-2">
                                      <label for="date1">Od</label>
                                      <input id="date1" name="Date1" type="text" value="'.($parsed["Date1"]??'').'" pattern="(?:19|20)[0-9]{2}-(?:(?:0[1-9]|1[0-2])-(?:0[1-9]|1[0-9]|2[0-9])|(?:(?!02)(?:0[1-9]|1[0-2])-(?:30))|(?:(?:0[13578]|1[02])-31))" required title="formát yyyy-mm-dd">
                                  </div>
                                  <div class="pure-u-1-2">
                                      <label for="date2">Do</label>
                                      <input id="date2" name="Date2" type="text" value="'.($parsed["Date2"]??'').'" pattern="(?:19|20)[0-9]{2}-(?:(?:0[1-9]|1[0-2])-(?:0[1-9]|1[0-9]|2[0-9])|(?:(?!02)(?:0[1-9]|1[0-2])-(?:30))|(?:(?:0[13578]|1[02])-31))" title="formát yyyy-mm-dd">
                                  </div>
                                  <div class="pure-u-1-2">
                                      <label for="place">Místo</label>
                                      <input id="place" name="Place" type="text" value="'.($parsed["Place"]??'').'">
                                  </div>
                                  <div class="pure-u-1-2">
                                      <label for="GPS">GPS</label>
                                      <input id="GPS" name="GPS" type="text" value="'.($parsed["GPS"]??'').'">
                                  </div>
                                  <div class="pure-u-1-2">
                                      <label for="meetTime">Sraz / čas</label>
                                      <input id="meetTime" name="MeetTime" type="text" value="'.($parsed["MeetTime"]??'').'">
                                  </div>
                                  <div class="pure-u-1-2">
                                      <label for="meetPlace">Sraz / místo</label>
                                      <input name="MeetPlace" type="text" value="'.($parsed["MeetPlace"]??'').'">
                                  </div>
                                  <div class="pure-u-1">
                                      <label for="transport">Doprava</label>
                                      <textarea id="transport" name="Transport" type="text" rows="1">'.($parsed["Transport"]??'').'</textarea>
                                  </div>
                              </div> <!-- pure-g -->
                          </div><!-- pure-u-10-24 --><!--
                       --><div class="pure-u-4-24">
                            &nbsp;
                          </div><!--
                       --><div class="pure-u-10-24">
                              <div class="pure-g">
                                <div class="pure-u-1">
                                    <br>
                                  <fieldset>
                                      <legend>Skupiny:</legend>
                                      <input name="Zabicky" type="hidden" value="0">
                                      <input id="zabicky" type="checkbox" name="Zabicky" value="1" '.(isset($parsed["Zabicky"])?"checked":"").'>
                                          <label for="zabicky"> žabičky </label> <br>
                                      <input name="Pulci1" type="hidden" value="0">
                                      <input id="pulci1" type="checkbox" name="Pulci1" value="1" '.(isset($parsed["Pulci1"])?"checked":"").'>
                                          <label for="pulci1"> pulci 1 </label> <br>
                                      <input name="Pulci2" type="hidden" value="0">
                                      <input id="pulci2" type="checkbox" name="Pulci2" value="1" '.(isset($parsed["Pulci2"])?"checked":"").'>
                                          <label for="pulci2"> pulci 2 </label> <br>
                                      <input name="Zaci1" type="hidden" value="0">
                                      <input id="zaci1" type="checkbox" name="Zaci1" value="1" '.(isset($parsed["Zaci1"])?"checked":"").'>
                                          <label for="zaci1"> žáci 1 </label> <br>
                                      <input name="Zaci2" type="hidden" value="0">
                                      <input id="zaci2" type="checkbox" name="Zaci2" value="1" '.(isset($parsed["Zaci2"])?"checked":"").'>
                                          <label for="zaci2"> žáci 2 </label> <br>
                                      <input name="Dorost" type="hidden" value="0">
                                      <input id="dorost" type="checkbox" name="Dorost" value="1" '.(isset($parsed["Dorost"])?"checked":"").'>
                                          <label for="dorost"> dorost+ </label>
                                  </fieldset>
                                </div>
                                <div class="pure-u-1">
                                    <label for="leader">Vedoucí</label>
                                    <input id="leader" name="Leader" type="text" value="'.($parsed["Leader"]??'').'">
                                </div>
                              </div> <!-- pure-g -->
                          </div> <!-- pure-u-10-24 -->
                          <div class="pure-u-1">
                                <label for="note">Poznámka</label>
                                <textarea id="note" name="Note" rows="1">'.($parsed["Note"]??'').'</textarea>
                          </div>';
            if(isset($parsed["Template"])){
                if($parsed["Template"]=="zavod"){
                    echo'<div class="pure-u-1">
                        <hr>
                            <label for="link">Odkaz na ORIS / stránky závodu</label>
                            <input id="link" name="Link" type="text" value="'.($parsed["Link"]??'').'">
                        </div>';
                }
            }
            if(isset($parsed["Date1"], $parsed["Date2"])){
                if($parsed["Date1"] != $parsed["Date2"]){
                    echo'<div class="pure-g pure-u-1">
                        <hr>
                        <div class="pure-u-10-24">
                            <label for="accomodation">Ubytování</label>
                            <textarea id="accomodation" name="Accomodation" type="text" rows="1">'.($parsed["Accomodation"]??'').'</textarea>
                        </div><!-- pure-u-10-24 --><!--
                     --><div class="pure-u-4-24">
                            &nbsp;
                        </div><!--
                     --><div class="pure-u-10-24">
                            <label for="food">Strava</label>
                            <textarea id="food" name="Food" type="text" rows="1">'.($parsed["Food"]??'').'</textarea>
                        </div> <!-- pure-u-10-24 -->
                    </div> <!-- pure-g -->';
                }
            }
            if(isset($parsed["Template"])){
                if($parsed["Template"]=="zavod" || $parsed["Template"]=="trenink"){
                    echo'<div class="pure-g pure-u-1">
                            <hr>
                            <div class="pure-u-10-24">
                                <div class="pure-g">
                                <div class="pure-u-1">
                                    <label for="startTime">Start</label>
                                    <input id="startTime" name="StartTime" type="text" value="'.($parsed["StartTime"]??'').'">
                                </div>
                                <div class="pure-u-1">
                                    <label for="eventTypeDescription">Tratě</label>
                                    <textarea id="eventTypeDescription" name="EventTypeDescription" type="text" rows="1">'.($parsed["EventTypeDescription"]??'').'</textarea>
                                </div>
                                </div> <!-- pure-g -->
                            </div><!-- pure-u-10-24 --><!--
                         --><div class="pure-u-4-24">
                              &nbsp;
                            </div><!--
                         --><div class="pure-u-10-24">
                                <div class="pure-u-1">
                                    <label for="map">Mapa</label>
                                    <input id="map" name="Map" type="text" value="'.($parsed["Map"]??'').'">
                                </div>
                                <div class="pure-u-1">
                                    <label for="terrain">Terén</label>
                                    <textarea id="terrain" name="Terrain" type="text" rows="3">'.($parsed["Terrain"]??'').'</textarea>
                                </div>
                            </div> <!-- pure-u-10-24 -->
                        </div> <!-- pure-g -->';
                }
            }
            if(isset($parsed["Template"])){
                if($parsed["Template"]=="soustredeni"){
                    echo'<div class="pure-u-1" id="soustredeni">
                            <hr>
                            <div class="pure-g">
                                <div class="pure-u-1">
                                    <label for="signups">Přihlášky</label>
                                    <input id="signups" name="Signups" type="text" value="'.($parsed["Signups"]??'').'">
                                </div>
                                <div class="pure-u-1">
                                    <label for="price">Cena</label>
                                    <textarea id="price" name="Price">'.($parsed["Price"]??'').'</textarea>
                                </div>
                                <div class="pure-u-1">
                                    <label for="return">Návrat</label>
                                    <textarea id="return" name="Return">'.($parsed["Return"]??'').'</textarea>
                                </div>
                                <div class="pure-u-1">
                                    <label for="program">Náplň / Program</label>
                                    <textarea id="program" name="Program">'.($parsed["Program"]??'').'</textarea>
                                </div>
                                <div class="pure-u-1">
                                    <label for="thingsToTake">S sebou</label>
                                    <textarea id="thingsToTake" name="ThingsToTake">'.($parsed["ThingsToTake"]??'').'</textarea>
                                </div>
                            </div> <!-- pure-g -->
                        </div><!-- pure-u-1 id="soustredeni" -->';
                }
            }
                          
                    
              echo '<div class="pure-u-1">
                        <hr>
                    
                    
                    
                    <button id="saveEvent" type="submit">Uložit</button> <br>
                    <div id="formResponse"></div>
                </div>
                </div> <!-- pure-g -->
            </form>';
        }
        else{
          echo"<h2>Není zadána cesta k souboru.</h2>";
        }
        // javacript is saved normaly in page
    }
    public function phpSaveEditedEvent(){
      if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if(isset($_POST["POST_type"])){
            if( $_POST["POST_type"] == "editEvent" ){
                // kontrola doručení potřebných údajů
                if(!isset($_POST["Name"])){
                    header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
                    echo 'Není vyplněn "Název"';
                    die();
                }
                if(!isset($_POST["Date1"])){
                    header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
                    echo 'Není vyplněno "Datum"';
                    die();
                }
                if(!isset($_POST["Template"])){
                    header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
                    echo 'CHYBA!!, nebyl obdržen typ události [Template]';
                    die();
                }
                if(!isset($_POST["Id"])){
                    header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
                    echo 'CHYBA!!, nebylo obdrženo ID události [Id]';
                    die();
                }
                            

                $post_scheme = ["Date1", "Date2", "Name", "Place", "MeetTime", "MeetPlace", "Link", "EventTypeDescription", "StartTime", "Zabicky", "Pulci1", "Pulci2", "Zaci1", "Zaci2", "Dorost", "Map", "Terrain", "Transport", "Accomodation", "Food", "Leader", "DoWeOrganize", "Note", "Return", "Price", "Program", "ThingsToTake", "Signups", "Id"];
                $year = substr($_POST["Id"], 1 , 4);
                $template = $_POST['Template'];
                $path = "./user/pages/databaze/". $year ."/". $template ."/". $_POST["Id"] . "/".$template.".cs.md";
                $parsed_file = $this->parse_file($path);   //rozparsuje existujici soubor
                foreach($post_scheme as $attribute){
                  if(isset($_POST[$attribute])){
                    $parsed_file[$attribute] = $this->trim_all($_POST[$attribute]);
                  }
                }
                // normalize GPS
                if(isset($_POST["GPS"]) && trim($_POST["GPS"]) != ""){
                    $gps = $this->normalize_GPS($_POST["GPS"]);
                    if($gps){
                        $parsed_file["GPS"] = $gps;
                    }
                    else{
                        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
                        echo 'Nepodporovaný formát GPS';
                        die();
                    }
                }

                //print_r($_POST);
                //print_r($parsed_file);

                $this->array_to_file($parsed_file, $template, $path);
                Cache::clearCache('all');

                //echo"<script type='text/javascript'>window.location.replace(location.href);</script>";
            }
        }
      }
    }
//******** POLARIS *********
    function make_jpeg_thumbnail($source, $target){
        $source = realpath($source);
        $im = new \Imagick();
        $im->setResolution(595, 842);    // set loaded resolution
        $im->readImage($source."[0]");    // 0-first page, 1-second page
        $im->transformimagecolorspace(\Imagick::COLORSPACE_SRGB);     //CMYK to RGB
        $im->setImageBackgroundColor('#ffffff');                      //prevents black background
        $im = $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);   //merge layers
        $im->setimageformat("jpeg");                                  //converts to JPEG
        $im->setCompression(\Imagick::COMPRESSION_JPEG);
        $im->setCompressionQuality(80);
        $im->resizeImage(595, 842, \Imagick::FILTER_LANCZOS,1);       //set saved resolution
        $im->writeImage($target);                                     //save
        $im->clear();
        $im->destroy();
    }

    function save_polaris_PDF($savePath, $polarisFileName){
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['PDF']['tmp_name']);
        if ($mime != 'application/pdf') {
            header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
            echo 'Nahraný soubor není PDF!';
            die();
        }
        if(!is_dir($savePath)){
            mkdir($savePath);
        }
        $saveFilesPath = $savePath ."/". $polarisFileName;
        move_uploaded_file($file_tmp=$_FILES["PDF"]["tmp_name"], $saveFilesPath);

        $this->make_jpeg_thumbnail($saveFilesPath, $saveFilesPath . ".jpg");
    }

    public function SavePolaris(){ 

        // init vars
        $pagePath = $_POST['path'];
        $savePath = getcwd() . '/user/pages/databaze/polaris/' . $_POST['year'];
        $polarisYear = $_POST['year'];
        $polarisNumber = "p" . $_POST['cislo'];
        $polarisFileName = "Polaris_" . $_POST['year'] . "_" . $_POST['cislo'] . ".pdf" ;

        //get frontmatter
        $frontmatter = $this->get_frontmatter_as_array($pagePath);

        // add polaris to frontmatter
        if(isset($frontmatter['polaris']) && in_array ( $polarisFileName , $frontmatter['polaris'] ) ){
            header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
            echo "Už je nahrané stejné číslo Polarisu.";
            die();
        }
        else{
            $frontmatter['polaris'][$polarisYear][$polarisNumber] = $polarisFileName;
            krsort($frontmatter['polaris']);
        } 

        // save pdf and jpeg thumbnail
        $this->save_polaris_PDF($savePath, $polarisFileName);
        
        // build page
        $pageFrontmatter = Yaml::dump($frontmatter, 10);
        $pageContent = $this->parse_file_content_only($pagePath);
        $page = $this->combine_frontmatter_with_content($pageFrontmatter, $pageContent);

        // save page to file
        file_put_contents($pagePath, $page);
        Cache::clearCache('all');
    }

    public function DeletePolaris(){
    
        // init vars
        $polarisYear = $_POST['year'];
        $polarisNumber = "p" . $_POST['cislo'];
        $pagePath = $_POST['path'];
        $filePath = getcwd() . '/user/pages/databaze/polaris/' . $_POST['year']. "/" . $_POST['pdf'];
        
        // get frontmatter
        $frontmatter = $this->get_frontmatter_as_array($pagePath);

        // remove polaris from frontmatter
        unset($frontmatter['polaris'][$polarisYear][$polarisNumber]);

        // delete pdf and jpeg thumbnail
        if(file_exists($filePath)){
            unlink($filePath);
        }
        if(file_exists($filePath .".jpg")){
            unlink($filePath .".jpg");
        }

        // build page
        $pageFrontmatter = Yaml::dump($frontmatter, 10);
        $pageContent = $this->parse_file_content_only($pagePath);
        $page = $this->combine_frontmatter_with_content($pageFrontmatter, $pageContent);

        // save page to file
        file_put_contents($pagePath, $page);
        Cache::clearCache('all');
    }


}

?>
