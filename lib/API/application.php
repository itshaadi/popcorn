<?php
namespace API;
use Slim\Slim;

class Application extends slim{

	// handling errors

	public function throwError($code,$message,$status){

		$app = $this;
		$req = $app->request();

		$mediaType = $app->request->getMediaType();
		$isAPI = (bool) preg_match('|^/v.*$|', $app->request->getPath());

		$callback = $req->get('callback');

		if(trim($callback) != NULL AND preg_match('/^[a-zA-Z]*$/', $callback)){

			$error = array(
				'error_type' => $code,
				'message' => $message,
				);

			$error = $callback.'('.json_encode($error).');';

		}
		else{
			$error = array(
				'error_type' => $code,
				'message' => $message,
				);
			$error = json_encode($error);
		}

		$this->response->setStatus($status);

	    if ('application/json' === $mediaType || true === $isAPI) {
	        $app->response->headers->set(
	            'Content-Type',
	            'application/json'
	        );
	        echo $error;
	        $app->stop();
	    } else {
	        echo '<html>
	        <head><title>Error</title></head>
	        <body><h1>Error: ' . $error['code'] . '</h1><p>'
	        . $error['message']
	        .'</p></body></html>';
	        $app->stop();
	    }
	}

	//making crawlable urls from resources.

	public function makeUrl($uri,$parameters,$methode){
		/*Main variables*/
		$url;

		/*Methodes*/
		$findExact = array(
			'mutli' => "http://www.imdb.com/find?q=".urlencode($parameters['title'])."&s=tt&exact=true&ref_=fn_al_tt_ex",
			'movie' => "http://www.imdb.com/find?q=".urlencode($parameters['title'])."&s=tt&ttype=ft&exact=true&ref_=fn_tt_ex",
			'series' => "http://www.imdb.com/find?q=".urlencode($parameters['title'])."&s=tt&ttype=tv&exact=true"
			);

		$titleSearch = array(
		'baseUrl' => 'http://www.imdb.com/search/title?',
		'query' => "&title=".urlencode($parameters['title']),
		'onlyOne' => '&count=1',
		'year' => '&release_date='.urlencode($parameters['year']),
		'typeMulti' => '&title_type=feature,tv_movie,tv_series,mini_series,documentary,short,video',
		'typeMovie' => '&title_type=feature,video,documentary,short',
		'typeTV' => '&title_type=tv_series,tv_movie,mini_series',
		'restOfBase' => '&production_status=released,&sort=moviemeter&view=simple&count=10',
		);

		$findSimilars = array(
			'mutli' => "http://www.imdb.com/find?q=".urlencode($parameters['title'])."&s=tt&ref_=fn_al_tt_ex",
			'movie' => "http://www.imdb.com/find?q=".urlencode($parameters['title'])."&s=tt&ttype=ft&ref_=fn_tt_ex",
			'series' => "http://www.imdb.com/find?q=".urlencode($parameters['title'])."&s=tt&ttype=tv"
			);

		/*Making url for finding the exact match*/
		if($methode == 0){
			switch($uri){
				case "search/multi" :
				$url = $findExact['mutli'];
				break;
				case "search/movie" :
				$url = $findExact['movie'];
				break;
				case "search/tv" :
				$url = $findExact['series'];
				break;
				case "search/series" : 
				$url = $findExact['series'];
				break;
			}
		}
		elseif($methode == 1){
			switch($uri){
				case "search/multi" :
					if($parameters['year'] == NULL){
						$url = $titleSearch['baseUrl'].$titleSearch['query'].$titleSearch['typeMulti'].$titleSearch['restOfBase'];
					}
					else{
						$url = $titleSearch['baseUrl'].$titleSearch['query'].$titleSearch['year'].$titleSearch['typeMulti'].$titleSearch['restOfBase'];
					}
				break;
				case "search/movie" :
					if($parameters['year'] == NULL){
						$url = $titleSearch['baseUrl'].$titleSearch['query'].$titleSearch['typeMovie'].$titleSearch['restOfBase'];
					}
					else{
						$url = $titleSearch['baseUrl'].$titleSearch['query'].$titleSearch['year'].$titleSearch['typeMovie'].$titleSearch['restOfBase'];
					}
				break;
				case "search/tv" :
					if($parameters['year'] == NULL){
						$url = $titleSearch['baseUrl'].$titleSearch['query'].$titleSearch['typeTV'].$titleSearch['restOfBase'];
					}
					else{
						$url = $titleSearch['baseUrl'].$titleSearch['query'].$titleSearch['year'].$titleSearch['typeTV'].$titleSearch['restOfBase'];
					}
				break;
				case "search/series" : 
					if($parameters['year'] == NULL){
						$url = $titleSearch['baseUrl'].$titleSearch['query'].$titleSearch['typeTV'].$titleSearch['restOfBase'];
					}
					else{
						$url = $titleSearch['baseUrl'].$titleSearch['query'].$titleSearch['year'].$titleSearch['typeTV'].$titleSearch['restOfBase'];
					}
				break;
			}
		}
		elseif($methode == 2){
			switch($uri){
				case "search/multi" :
				$url = $findSimilars['mutli'];
				break;
				case "search/movie" :
				$url = $findSimilars['movie'];
				break;
				case "search/tv" :
				$url = $findSimilars['series'];
				break;
				case "search/series" : 
				$url = $findSimilars['series'];
				break;
			}
		}

		return $url;
	}

	// make JSON outputs clean and readable.

	public function jsonOutPut($uri,$results,$callback){
		$app = $this;
		$req = $app->request();
		$output;
		$json;

		if(preg_match('/subtitle/i', $uri)){

			$index = count($results['title']) - 1;

			for($i=0; $index >= $i; $i++){

				$data['search'][] = array(
						'title' => $results['title'][$i],
						'version' => $results['version'][$i],
						'translator' => $results['translator'][$i],
						'format' => $results['format'][$i],
						'url' => $results['url'][$i]
					);
			}
		}

		elseif(preg_match('/search/i', $uri)){

			$index = count($results['title']) - 1;

			for($i=0; $index >= $i; $i++){

				$data['search'][] = array(
						'title' => $results['title'][$i],
						'year' => $results['year'][$i],
						'imdbID' => $results['imdbID'][$i],
						'type' => $results['type'][$i]
					);
			}
		}

		elseif(preg_match('/title/i', $uri)){
			$values = array(
				0 => "title",
				1 => "year",
				2 => "contentRating",
				3 => "released",
				4 => "runtime",
				5 => "genre",
				6 => "director",
				7 => "writer",
				8 => "stars",
				9 => "plot",
				10 => "language",
				11 => "country",
				12 => "awards",
				13 => "imdbRating",
				14 => "imdbVotes",
				15 => "metascore",
				16 => "language",
				17 => "budget",
				18 => "gross",
				19 => "poster",
				20 => "trailer",
				21 => "type"
				);

		if(isset($results['plot'])){
			while(TRUE){
				if(substr($results['plot'], -1) == "."){
					break;
				}
				else{
					$results['plot'] = trim(substr($results['plot'], 0, -1));
				}
			}
		}

			if(empty(trim($results["released"]))){
				$results["released"] = "N/A";
				$results["year"] = "N/A";
			}
			else{
				while(TRUE){
					if(substr($results['released'], -1) == ")"){
						break;
					}
					else{
						$results['released'] = trim(substr($results['released'], 0, -1));
					}
				}

				$results['released'] = trim(str_replace("Release Date:", '', $results['released']));
				$results['released'] = trim(preg_replace('/\(([^\)]+)\)/', '', $results['released']));
				preg_match_all('/(19|20)\d{2}$/', $results['released'], $matches);
				$results['year'] = implode('', $matches[0]);
			}

			$index = count($values) - 1;

			for($i=0; $index >= $i; $i++){

				if( !isset( $results[$values[$i]] ) ){
					$results[$values[$i]] = "N/A";
				}
				
				$json[$values[$i]] = $results[$values[$i]];
			}

			$data = $json;
		}

		elseif(preg_match('/person/i', $uri)){
			$values = array(
				0 => "name",
				1 => "careers",
				2 => "bio",
				3 => "height",
				4 => "bornName",
				5 => "birthDate",
				6 => "birthPlace",
				7 => "deathDate",
				8 => "deathPlace",
				9 => "awards",
				10 => "image",
				);

		if(isset($results['bio'])){
			while(TRUE){
				if(substr($results['bio'], -1) == "."){
					break;
				}
				else{
					$results['bio'] = trim(substr($results['bio'], 0, -1));
				}
			}
		}

			$index = count($values) - 1;

			for($i=0; $index >= $i; $i++){

				if( !isset( $results[$values[$i]] ) ){
					$results[$values[$i]] = "N/A";
				}
				
				$json[$values[$i]] = $results[$values[$i]];
			}

			$data = $json;
		}
		elseif(preg_match('/list\/boxoffice/i', $uri)){
			$index = count($results['title']) -1;

			for($i=0; $index >= $i; $i++){	
				$json[] = array(
						'rank' => $results['rank'][$i],
						'title' => $results['title'][$i],
						'weeksReleased' => $results['weeksReleased'][$i],
						'weekendGross' => $results['weekendGross'][$i],
						'totalGross' => $results['totalGross'][$i]
					);
			}

			$data = $json;
		}

		if(trim($callback) != NULL){
			$output = $callback.'('.json_encode($data,JSON_UNESCAPED_UNICODE).');';
		}
		else{
			$output = json_encode($data,JSON_UNESCAPED_UNICODE);
		}

		return $output;
	}

	//Because of Internet restrictions in Iran, trailers servers are restricted. We had to create a function for checking out that for Iranian server.

	public function is_denied($url){
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);

    curl_setopt($ch, CURLOPT_HEADER, TRUE);

    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

    $a = curl_exec($ch);

    return strpos($a , 'http://10.10.34.34')  ? true : false;
	}

	public function get_data($url) {
	$ch = curl_init();
	$timeout = 5;
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
	curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, false);
	$data = curl_exec($ch);
	curl_close($ch);
	return $data;
}

}