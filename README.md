# scrapethisclass
This class uses curl to scrape the entire contnet of a page, parses it down until just an image url remains, passes it to another curl function that downloads the image to a local dir and names the file.Meant to be run with a cron job on the controller. 
