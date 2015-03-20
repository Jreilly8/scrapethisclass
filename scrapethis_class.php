<?php

/* 

Description:
The main function of the class is doThis(), this class uses curl to scrape the entire contnet of a page, parses it down
until just an image url remains, passes it to another curl function that downloads the image to a local dir and names the file.
Meant to be run with a cron job on the controller. 

Usage:
Setup cruon job to run controller at 12 hour intervals.
Set the variables in the controller.
$url: The web page to be scraped.
$path: The dir where the image will be stored and name the image will be saved as. Ex: mypath/filename.jpg
$mode: Used to select between Mas sites & Aebn, it effects the functions and content of the scrape.
$base_url: A base url for concantonating relative image urls.

Notes:
Any new type of scrape should be added in a new switch case. 
The path to the log needs to be changed when moved.
Setup cronjob to run controller.


Example:

$scrape1 = new ScrapeThis;
$scrape1->doThis('http://scrapethis.com/index.php', 'images/that.jpg', 'mas', 'http://domain.com/');

or aebn scape with out the base_url set because the url is compleate

$scrape3 = new ScrapeThis;
$scrape3->doThis('http://scrapethis.com/index.php'', 'images/this.jpg', 'aebn', '');

*/ 

class ScrapeThis{

    // Error log path
    var $log_file = "/var/www/sites/www.yourdomain.com/fullpath//watchnowauto/scrapeThis.log";

    var $overlay_background = '/var/www/sites/www.yourdomain.com/fullpath/box_bkgnd.png';
    var $resize_button = '/var/www/sites/www.yourdomain.com/fullpath/image.png';

    //Resizing params for overlay
    var $overlay_options = array(
        'dst_x'     => 90,     //overlay x coord
        'dst_y'     => 20,     //overlay y coord
        'dst_w'     => 135,    //overlay width
        'dst_h'     => 191,    //overlay height
        'quality'   => 100   //output quality
    );

    //Resizing params for slider resize
    var $resize_options = array(
        'dst_w'     => 981,    //new width
        'dst_h'     => 545,    //new height
        'button_x'  => 800,    //button left
        'button_y'  => 450,    //button top
        'quality'   => 100   //output quality
    );

    // Error flag
    var $fatal_error = FALSE;
             
    // url of the site to be scrapped set in the contoller
    var $url;
    
    //image dir and filename set in contoller 
    var $saveto;

    //path to processed output image
    var $output_img;

    //Selects the mode of the scrape type set in contoller 
    var $mode;

    //Used for concatonating and compleating the image urls parsed from mas  
    var $base_url;
    
    //Scraped page content
    var $content;
    
    //URL of image = $content after parsed by isolateThis() into link needed for download.
    var $image_link;




    function doThis($url, $saveto, $output_img, $mode, $base_url)
    {
        //execute functions of ScrapeThis class
        $this->url = $url;
        $this->saveto = $saveto;
        $this->output_img = $output_img;
        $this->mode = $mode;
        $this->base_url = $base_url;
        $this->curl();

        if( ! $this->fatal_error)
            $this->isolateThis();

        if( ! $this->fatal_error)
            $this->downloadThis();

        if( ! $this->fatal_error)
            $this->manipulateThis();

    }
    
    function curl()
    {
        
        // cURL options1  for cURL page scrape  $ch1 in curl()

        $options = Array(
            CURLOPT_RETURNTRANSFER => 1,  // Setting cURL's option to return the webpage data
            CURLOPT_FOLLOWLOCATION => 1,  // Setting cURL to follow 'location' HTTP headers
            CURLOPT_AUTOREFERER => 1, // Automatically set the referer where following 'location' HTTP headers
            CURLOPT_CONNECTTIMEOUT => 120,   // Setting the amount of time (in seconds) before the request times out
            CURLOPTTIMEOUT => 120,  // Setting the maximum amount of time for cURL to execute queries
            CURLOPT_USERAGENT => "Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.9.1a2pre) Gecko/2008073000 Shredder/3.0a2pre ThunderBrowse/3.2.1.8",  // Setting the useragent
            CURLOPT_URL => $this->url, // Setting cURL's URL option with the $url variable passed into the function
        );

        //use cURL to connect to site and scrape

        $ch = curl_init();  // Initialising cURL 
        curl_setopt_array($ch, $options);   // Setting cURL's options using the previously assigned array content in $options
        $this->content = curl_exec($ch); // Executing the cURL request and assigning the returned content to the $content variable
        curl_close($ch);    // Closing cURL 
        if( ! $this->content)   // Returning the content from the function
        {
            $this->log('cURL failed to get content');
            $this->fatal_error = true;
        }

    }

   
    function isolateThis()
    {
        //Strip away unwanted data before and after target, in this case is the image link
        
        $data = $this->content;

        switch ($this->mode) {
            case 'mas':
                // Member sites image link isolation         
                $start = '#trailer-vv-popup1"><img src="';
                $end = '" />';
                $data = stristr($data, $start);
                $data = substr($data, strlen($start));
                $stop = stripos($data, $end);
                $data = substr($data, 0, $stop);
                $data = $this->base_url . $data;
                $this->image_link = $data;
                $this->log($data);

                break;

            case 'aebn':
                // AEBN image link isolation

                $regular_expression = '/<div class="movieBoxCover">\s*<[^>]+>\s*<img src="([^"]+)"/';

                if (preg_match($regular_expression, $data, $regs)) {
                    $data = $regs[1];
                    $this->image_link = $data;
                    $this->log($data);
                } else {
                    //error
                    $this->log('WTF Expression not found');
                }
                break;

            case 'slide':
                // Member sites image link isolation
                
                $start = '<!-- start slider1--><img src="';
                $end = '"><!-- end slider1-->';
                $data = stristr($data, $start);
                $data = substr($data, strlen($start));
                $stop = stripos($data, $end);
                $data = substr($data, 0, $stop);
                $data = $this->base_url . $data;
                $this->image_link = $data;
                $this->log($data);

                break;           

            default:
                // Maybe an error flag?
                $this->log('Mode not selected, or other error in method isolateThis');
                $this->fatal_error = true;
                break;
        }   
    }
      
    function downloadThis()
    {
        
        //cURL options2 for image downlaod $ch in downloadThis()
        $options2 = Array(
        CURLOPT_HEADER => 0,  // Setting cURL's option to return the webpage data
        CURLOPT_RETURNTRANSFER => 1,  // Setting cURL to follow 'location' HTTP headers
        CURLOPT_BINARYTRANSFER => 1, // Automatically set the referer where following 'location' HTTP headers
        );     
        $ch = curl_init($this->image_link); // initate curl
        curl_setopt_array($ch, $options2); // set curl options
        $now = curl_exec($ch); // start curl query
        curl_close($ch);
        $fp = fopen($this->saveto,'w'); 
        fwrite($fp, $now);
        fclose($fp);
    }


    function manipulateThis()
    {
        //Jake's cool image tweaking
        switch ($this->mode) {
            case 'mas':
            case 'aebn':
                $this->overlay_boxcovers();
                $this->log('boxcover overlayed');
                break;

            case 'slide':
                $this->resize_slider();
                $this->log('slider resized');
                break;

            default:
                $this->log('Mode not selected');
                $this->fatal_error = true;
                break;
        }
    }

    //Overlay a box cover over the background image
    function overlay_boxcovers() { 
        $dst_image =                                //Destination image link resource
            imagecreatefrompng(
                $this->overlay_background
            );      
        $src_image =                                //Source image link resource
            imagecreatefromjpeg(
                $this->image_link
            );
        $dst_x = $this->overlay_options['dst_x'];   //x-coordinate of destination point
        $dst_y = $this->overlay_options['dst_y'];   //y-coordinate of destination point
        $src_x = 0;                                 //x-coordinate of source point
        $src_y = 0;                                 //y-coordinate of source point
        $dst_w = $this->overlay_options['dst_w'];   //Destination width
        $dst_h = $this->overlay_options['dst_h'];   //Destination height
        list(
            $src_w,                                 //Source width
            $src_h                                  //Source height
        ) = getimagesize($this->image_link);

        imagecopyresampled($dst_image,$src_image,$dst_x,$dst_y,
            $src_x,$src_y,$dst_w,$dst_h,$src_w,$src_h);

        imagejpeg($dst_image, $this->output_img, $this->overlay_options['quality']);
    }

    //Resize a slider
    function resize_slider() {
        $dst_image =                                //Destination image link resource
            imagecreatetruecolor(           
                $this->resize_options['dst_w'], 
                $this->resize_options['dst_h']
            );
        $src_image =                                //Source image link resource
            imagecreatefromjpeg(            
                $this->image_link
            );  
        $dst_x = 0;                                 //x-coordinate of destination point
        $dst_y = 0;                                 //y-coordinate of destination point
        $src_x = 0;                                 //x-coordinate of source point
        $src_y = 0;                                 //y-coordinate of source point
        $dst_w = $this->resize_options['dst_w'];    //Destination width
        $dst_h = $this->resize_options['dst_h'];    //Destination height
        list(
            $src_w,                                 //Source width
            $src_h                                  //Source height
        ) = getimagesize($this->image_link);

        imagecopyresampled($dst_image,$src_image,$dst_x,$dst_y,
            $src_x,$src_y,$dst_w,$dst_h,$src_w,$src_h);

        /* Add button overlay*/
        //$dst_image = $dst_image;
        $src_image =                                //Source image link resource
            imagecreatefrompng(
                $this->resize_button
            );  
        $dst_x = $this->resize_options['button_x']; //x-coordinate of destination point
        $dst_y = $this->resize_options['button_y']; //y-coordinate of destination point
        $src_x = 0;                                 //x-coordinate of source point
        $src_y = 0;                                 //y-coordinate of source point   
        list(
            $dst_w,                                 //Destination width
            $dst_h                                  //Destination height
        ) = getimagesize($this->resize_button);
        $src_w = $dst_w;                            //Source width
        $src_h = $dst_h;                            //Source height

        imagecopyresampled($dst_image,$src_image,$dst_x,$dst_y,
            $src_x,$src_y,$dst_w,$dst_h,$src_w,$src_h);

        /*Write result to disk*/

        imagejpeg($dst_image, $this->output_img, $this->resize_options['quality']);
    }
   
    function log($msg)
    {
        $date = date('Y-m-d H:i:s');

        //log to file
        $fh = fopen($this->log_file, 'a');
        fwrite($fh,$date." ".$msg."\n");
        fclose($fh);
    }
}

?>
