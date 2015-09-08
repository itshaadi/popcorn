<?php
/*
Copyright © 2015 mohamad hadi azami iazami@outlook.com

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the “Software”),
to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense,
and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, 
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

NOTE: 10.10.34.34 refers to peyvandha.ir and nfqq.nvswi2lb(blah blah blah).nblk.ru refers to IMDB.COM 
some results may be censored. (so it's a proxy server)

*/
require_once dirname(__FILE__) . '/bootstrap.php';
require_once dirname(__FILE__) . '/lib/simpleHtmlDom.php';
require_once dirname(__FILE__). '/errors.php';

// validation middleware

$validation = function ( $parameters,$app ) {
    return function () use ( $parameters,$app ) {

    	$req = $app->request();
    	$uri =  str_replace('/v1/', '', $req->getResourceUri());

		if(isset($parameters['callBack']) && trim($parameters['callBack']) == NULL){
			$app->throwError("invalid_callback","The query parameter 'callBack' can't be empty when it's called.",400);
		}
		elseif(!preg_match('/^[a-zA-Z]*$/', $parameters['callBack'])){
			$app->throwError("invalid_callback","Callback name needs to be character (a-z|A-Z).",400);
		}

    	if(preg_match('/search/i', $uri)){

			if(!isset($parameters['title']) or trim($parameters['title']) == NULL){
				$app->throwError("invalid_title","The query parameter 'title' can't be empty.",400);
			}

			if(isset($parameters['year']) && trim($parameters['year']) != NULL){
				if(!preg_match('/^(19|20)\d{2}$/', $parameters['year'])){
					$app->throwError("invalid_year","Format needs to be YYYY (19|20).",400);
				}
			}
			elseif(isset($parameters['year']) && trim($parameters['year']) == NULL){
				$app->throwError("invalid_year","The query parameter 'year' can't be empty when it's called.",400);
			}

			if(isset($parameters['episode']) && trim($parameters['episode']) == NULL){
				$app->throwError("invalid_episode","The query parameter 'episode' can't be empty when it's called.",400);
			}

			if(isset($parameters['season']) && trim($parameters['season']) == NULL){
				$app->throwError("invalid_season","The query parameter 'season' can't be empty when it's called.",400);
			}

    	}
    };
};

 // Group for API Version 1

$app->group('/v1',function() use($app,$validation){

	$req = $app->request();
	$variable = array();
	$parameters = array(
		// required parameters
		'title' => $req->get('title'),
		//optional parameters
		'year' => $req->get('year'),
		'episode' => $req->get('episode'),
		'season' => $req->get('season'),
		'type' => $req->get('type'),
		'callBack' => $req->get('callback')
		);

 // Group for SEARCH
	$app->group('/search',function() use($app,$req,$parameters,$validation){

		$app->get('/multi', $validation($parameters,$app) ,function() use($app,$req,$parameters){
			/*Main variables*/
			$c=0;
			$results;
			$rq = $req;
			$flag = array(TRUE,TRUE,TRUE);
			$year = $parameters['year'];
			$uri =  str_replace('/v1/', '', $req->getResourceUri());
			$crawling = array(
				0 => file_get_html($app->makeUrl($uri,$parameters,0)),
				1 => file_get_html($app->makeUrl($uri,$parameters,1)),
				2 => file_get_html($app->makeUrl($uri,$parameters,2))
			 );

			/*start crawling*/
			foreach ($crawling[0]->find('.findHeader') as $element) {
				if(preg_match('/No results found for/i', $element->innertext)){
					$flag[0] = FALSE;
				}
			}
			foreach ($crawling[1]->find('div[id=main]') as $element) {
				if(preg_match('/ No results. /i', $element->innertext)){
					$flag[1] = FALSE;
				}
			}
			foreach ($crawling[2]->find('.findHeader') as $element) {
				if(preg_match('/No results found for/i', $element->innertext)){
					$flag[2] = FALSE;
				}
			}
			/*Crawling methode one (find exact title using imdb search metode)*/
			if($flag[1] == TRUE){

				/* fetching title and imdbID */
				foreach($crawling[1]->find('table[class=results] td[class=title]') as $element){

					foreach($element->find('a') as $items){

							$results['title'][] = html_entity_decode($items->innertext,ENT_QUOTES);

							$results['imdbID'][] = str_replace(array('/','title'), '', $items->href);
					}
				}
				/* fetching year */
				foreach($crawling[1]->find('table[class=results] td[class=title] span[class=year_type]') as $items){

					preg_match_all('/\d+/', $items->innertext, $matches);

					$results['year'][] = $matches[0][0];

				}

				/* fetching type of title */
				foreach($crawling[1]->find('table[class=results] td[class=title] span[class=year_type]') as $items){

					if(preg_match('/TV Series/i', $items->innertext)){
						$results['type'][] = 'TV Series';
					}
					elseif(preg_match('/TV Movie/i', $items->innertext)){
						$results['type'][] = 'TV Movie';
					}
					elseif(preg_match('/Mini-Series/i', $items->innertext)){
						$results['type'][] = 'Mini-Series';
					}
					elseif(preg_match('/Documentary/i', $items->innertext)){
						$results['type'][] = 'Documentary';
					}
					elseif(preg_match('/Short Film/i', $items->innertext)){
						$results['type'][] = 'Short Film';
					}
					elseif(preg_match('/Video/i', $items->innertext)){
						$results['type'][] = 'Video';
					}
					else{
						$results['type'][] = 'Movie';
					}
				}		
			}
			/* Ceawling methode two (find exact title using imdb find methode) */
			elseif($flag[0] == TRUE){
 				foreach($crawling[0]->find('table[class=findList] tr[class=findResult] td[class=result_text]') as $element){

 					$entries['plaintext'][] = $element->plaintext;
					$entries['link'][] = $element->find('a',0)->href;
					$results['title'][] = html_entity_decode($element->find('a',0)->innertext,ENT_QUOTES);

					if($c==10){
						break;
					}
					$c++;
				}
				$indexs = count($entries['plaintext']) - 1;
				for($i = 0; $indexs >= $i; $i++){

					/*fetching year*/
					if(preg_match('/\(\d+\)/', $entries['plaintext'][$i],$matches)){
						$results['year'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					else{
						$results['year'][] = 'N/A';
					}
					/*fetching type*/
					if(preg_match('/\(\TV Episode\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\TV Series\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\TV Special\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\TV Movie\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\TV Mini-Series\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\Video\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\Video Game\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\Short\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = "Short Film";
					}
					elseif(preg_match('/\(\Documentary\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					else{
						$results['type'][] = "Movie";
					}
					/*fetching imdb id*/
					if(preg_match('/\/tt\d+\//', $entries['link'][$i],$matches)){
						$results['imdbID'][] = str_replace('/', '', $matches[0]);
					}
				}

				$indexs = count($results['title']) - 1;

				/* removing TV Episode - Video Game and indevelopment results form search */
				for($i=0; $indexs >= $i; $i++){

					if($results['type'][$i] == 'in development'){
						unset($results['title'][$i]);
						unset($results['type'][$i]);
						unset($results['imdbID'][$i]);
						unset($results['year'][$i]);

						continue;

					}

					if($results['year'][$i] == 'N/A'){
						unset($results['title'][$i]);
						unset($results['type'][$i]);
						unset($results['imdbID'][$i]);
						unset($results['year'][$i]);

						continue;

					}

					if($results['type'][$i] == 'TV Episode'){

						unset($results['title'][$i]);
						unset($results['type'][$i]);
						unset($results['imdbID'][$i]);
						unset($results['year'][$i]);

						continue;

					}

					if($results['type'][$i] == 'Video Game'){

						unset($results['title'][$i]);
						unset($results['type'][$i]);
						unset($results['imdbID'][$i]);
						unset($results['year'][$i]);

						continue;

					}

				}
				/* pull back */
				$results['title'] = array_values($results['title']);
				$results['type'] = array_values($results['type']);
				$results['imdbID'] = array_values($results['imdbID']);
				$results['year'] = array_values($results['year']);

				$indexs = count($results['title']) - 1;
				/* removing NOT Relased Results */
				for($i=0; $indexs >= $i; $i++){

					$crawling[3] = file_get_html("http://www.imdb.com/title/".$results['imdbID'][$i]);

					foreach($crawling[3]->find('td[id=overview-top] div[class=star-box-rating-widget]') as $element){

						if(preg_match('/Not yet released/i', $element->plaintext)){
							unset($results['title'][$i]);
							unset($results['type'][$i]);
							unset($results['imdbID'][$i]);
							unset($results['year'][$i]);

							$results['title'] = array_values($results['title']);
							$results['type'] = array_values($results['type']);
							$results['imdbID'] = array_values($results['imdbID']);
							$results['year'] = array_values($results['year']);

							$indexs --;
							$i --;
						}
					}
				}

				/* removing results that did not match with query parameter 'year' */
				if(trim($year) != null){

					for($i=0; $indexs >= $i; $i++){

						if($results['year'][$i] != $year){

							unset($results['title'][$i]);
							unset($results['type'][$i]);
							unset($results['imdbID'][$i]);
							unset($results['year'][$i]);

							$results['title'] = array_values($results['title']);
							$results['type'] = array_values($results['type']);
							$results['imdbID'] = array_values($results['imdbID']);
							$results['year'] = array_values($results['year']);

							$indexs --;
							$i --;
						}
					}
				}
			}// end of methode 'find Exact'

			/* Ceawling methode three (find similar titles using imdb find methode) */
			elseif($flag[2] == TRUE){
 				foreach($crawling[2]->find('table[class=findList] tr[class=findResult] td[class=result_text]') as $element){

 					$entries['plaintext'][] = $element->plaintext;
					$entries['link'][] = $element->find('a',0)->href;
					$results['title'][] = html_entity_decode($element->find('a',0)->innertext,ENT_QUOTES);

					if($c==10){
						break;
					}
					$c++;
				}
				$indexs = count($entries['plaintext']) - 1;
				for($i = 0; $indexs >= $i; $i++){

					/*fetching year*/
					if(preg_match('/\(\d+\)/', $entries['plaintext'][$i],$matches)){
						$results['year'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					else{
						$results['year'][] = 'N/A';
					}
					/*fetching type*/
					if(preg_match('/\(\TV Episode\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\TV Series\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\TV Special\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\TV Movie\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\TV Mini-Series\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\Video\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\Video Game\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\Short\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\Documentary\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					else{
						$results['type'][] = "Movie";
					}
					/*fetching imdb id*/
					if(preg_match('/\/tt\d+\//', $entries['link'][$i],$matches)){
						$results['imdbID'][] = str_replace('/', '', $matches[0]);
					}
				}

				$indexs = count($results['title']) - 1;

				/* removing TV Episode - Video Game and indevelopment results form search */
				for($i=0; $indexs >= $i; $i++){

					if($results['type'][$i] == 'in development'){
						unset($results['title'][$i]);
						unset($results['type'][$i]);
						unset($results['imdbID'][$i]);
						unset($results['year'][$i]);

						continue;

					}

					if($results['year'][$i] == 'N/A'){
						unset($results['title'][$i]);
						unset($results['type'][$i]);
						unset($results['imdbID'][$i]);
						unset($results['year'][$i]);

						continue;

					}

					if($results['type'][$i] == 'TV Episode'){

						unset($results['title'][$i]);
						unset($results['type'][$i]);
						unset($results['imdbID'][$i]);
						unset($results['year'][$i]);

						continue;

					}

					if($results['type'][$i] == 'Video Game'){

						unset($results['title'][$i]);
						unset($results['type'][$i]);
						unset($results['imdbID'][$i]);
						unset($results['year'][$i]);

						continue;

					}

				}
				/* pull back */
				$results['title'] = array_values($results['title']);
				$results['type'] = array_values($results['type']);
				$results['imdbID'] = array_values($results['imdbID']);
				$results['year'] = array_values($results['year']);

				$indexs = count($results['title']) - 1;
				/* removing NOT Relased Results */
				for($i=0; $indexs >= $i; $i++){

					$crawling[3] = file_get_html("http://www.imdb.com/title/".$results['imdbID'][$i]);

					foreach($crawling[3]->find('td[id=overview-top] div[class=star-box-rating-widget]') as $element){

						if(preg_match('/Not yet released/i', $element->plaintext)){
							unset($results['title'][$i]);
							unset($results['type'][$i]);
							unset($results['imdbID'][$i]);
							unset($results['year'][$i]);

							$results['title'] = array_values($results['title']);
							$results['type'] = array_values($results['type']);
							$results['imdbID'] = array_values($results['imdbID']);
							$results['year'] = array_values($results['year']);

							$indexs --;
							$i --;
						}
					}
				}

				/* removing results that did not match with query parameter 'year' */
				if(trim($year) != null){

					for($i=0; $indexs >= $i; $i++){

						if($results['year'][$i] != $year){

							unset($results['title'][$i]);
							unset($results['type'][$i]);
							unset($results['imdbID'][$i]);
							unset($results['year'][$i]);

							$results['title'] = array_values($results['title']);
							$results['type'] = array_values($results['type']);
							$results['imdbID'] = array_values($results['imdbID']);
							$results['year'] = array_values($results['year']);

							$indexs --;
							$i --;
						}
					}
				}
			}// end of methode 'find similars'
			else{
				$app->throwError('not_found','The requested resource does not exist or is not publicly available.',404);
			}
			echo $app->jsonOutPut($uri,$results,$parameters['callBack']);
		}); // end multi search

		$app->get('/movie', $validation($parameters,$app) ,function() use($app,$req,$parameters){
			/*Main variables*/
			$c=0;
			$results;
			$rq = $req;
			$flag = array(TRUE,TRUE,TRUE);
			$year = $parameters['year'];
			$uri =  str_replace('/v1/', '', $req->getResourceUri());
			$crawling = array(
				0 => file_get_html($app->makeUrl($uri,$parameters,0)),
				1 => file_get_html($app->makeUrl($uri,$parameters,1)),
				2 => file_get_html($app->makeUrl($uri,$parameters,2))
			);

			/*start crawling*/
			foreach ($crawling[0]->find('.findHeader') as $element) {
				if(preg_match('/No results found for/i', $element->innertext)){
					$flag[0] = FALSE;
				}
			}
			foreach ($crawling[1]->find('div[id=main]') as $element) {
				if(preg_match('/ No results. /i', $element->innertext)){
					$flag[1] = FALSE;
				}
			}
			foreach ($crawling[2]->find('.findHeader') as $element) {
				if(preg_match('/No results found for/i', $element->innertext)){
					$flag[2] = FALSE;
				}
			}
			/*Crawling methode one (find exact title using imdb search metode)*/
			if($flag[1] == TRUE){

				/* fetching title and imdbID */
				foreach($crawling[1]->find('table[class=results] td[class=title]') as $element){

					foreach($element->find('a') as $items){

							$results['title'][] = html_entity_decode($items->innertext,ENT_QUOTES);

							$results['imdbID'][] = str_replace(array('/','title'), '', $items->href);
					}
				}
				/* fetching year */
				foreach($crawling[1]->find('table[class=results] td[class=title] span[class=year_type]') as $items){

					preg_match_all('/\d+/', $items->innertext, $matches);

					$results['year'][] = $matches[0][0];

				}

				/* fetching type of title */
				foreach($crawling[1]->find('table[class=results] td[class=title] span[class=year_type]') as $items){

					if(preg_match('/TV Series/i', $items->innertext)){
						$results['type'][] = 'TV Series';
					}
					elseif(preg_match('/TV Movie/i', $items->innertext)){
						$results['type'][] = 'TV Movie';
					}
					elseif(preg_match('/Mini-Series/i', $items->innertext)){
						$results['type'][] = 'Mini-Series';
					}
					elseif(preg_match('/Documentary/i', $items->innertext)){
						$results['type'][] = 'Documentary';
					}
					elseif(preg_match('/Short Film/i', $items->innertext)){
						$results['type'][] = 'Short Film';
					}
					elseif(preg_match('/Video/i', $items->innertext)){
						$results['type'][] = 'Video';
					}
					else{
						$results['type'][] = 'Movie';
					}
				}		
			}
			/* Ceawling methode two (find exact title using imdb find methode) */
			elseif($flag[0] == TRUE){
 				foreach($crawling[0]->find('table[class=findList] tr[class=findResult] td[class=result_text]') as $element){

 					$entries['plaintext'][] = $element->plaintext;
					$entries['link'][] = $element->find('a',0)->href;
					$results['title'][] = html_entity_decode($element->find('a',0)->innertext,ENT_QUOTES);

					if($c==10){
						break;
					}
					$c++;
				}
				$indexs = count($entries['plaintext']) - 1;
				for($i = 0; $indexs >= $i; $i++){

					/*fetching year*/
					if(preg_match('/\(\d+\)/', $entries['plaintext'][$i],$matches)){
						$results['year'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					else{
						$results['year'][] = 'N/A';
					}
					/*fetching type*/
					if(preg_match('/\(\TV Episode\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\TV Series\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\TV Special\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\TV Movie\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\TV Mini-Series\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\Video\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\Video Game\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\Short\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = "Short Film";
					}
					elseif(preg_match('/\(\Documentary\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					else{
						$results['type'][] = "Movie";
					}
					/*fetching imdb id*/
					if(preg_match('/\/tt\d+\//', $entries['link'][$i],$matches)){
						$results['imdbID'][] = str_replace('/', '', $matches[0]);
					}
				}

				$indexs = count($results['title']) - 1;

				/* removing TV Episode - Video Game and indevelopment results form search */
				for($i=0; $indexs >= $i; $i++){

					if($results['type'][$i] == 'in development'){
						unset($results['title'][$i]);
						unset($results['type'][$i]);
						unset($results['imdbID'][$i]);
						unset($results['year'][$i]);

						continue;

					}

					if($results['year'][$i] == 'N/A'){
						unset($results['title'][$i]);
						unset($results['type'][$i]);
						unset($results['imdbID'][$i]);
						unset($results['year'][$i]);

						continue;

					}

					if($results['type'][$i] == 'TV Episode'){

						unset($results['title'][$i]);
						unset($results['type'][$i]);
						unset($results['imdbID'][$i]);
						unset($results['year'][$i]);

						continue;

					}

					if($results['type'][$i] == 'Video Game'){

						unset($results['title'][$i]);
						unset($results['type'][$i]);
						unset($results['imdbID'][$i]);
						unset($results['year'][$i]);

						continue;

					}

				}
				/* pull back */
				$results['title'] = array_values($results['title']);
				$results['type'] = array_values($results['type']);
				$results['imdbID'] = array_values($results['imdbID']);
				$results['year'] = array_values($results['year']);

				$indexs = count($results['title']) - 1;
				/* removing NOT Relased Results */
				for($i=0; $indexs >= $i; $i++){

					$crawling[3] = file_get_html("http://www.imdb.com/title/".$results['imdbID'][$i]);

					foreach($crawling[3]->find('td[id=overview-top] div[class=star-box-rating-widget]') as $element){

						if(preg_match('/Not yet released/i', $element->plaintext)){
							unset($results['title'][$i]);
							unset($results['type'][$i]);
							unset($results['imdbID'][$i]);
							unset($results['year'][$i]);

							$results['title'] = array_values($results['title']);
							$results['type'] = array_values($results['type']);
							$results['imdbID'] = array_values($results['imdbID']);
							$results['year'] = array_values($results['year']);

							$indexs --;
							$i --;
						}
					}
				}

				/* removing results that did not match with query parameter 'year' */
				if(trim($year) != null){

					for($i=0; $indexs >= $i; $i++){

						if($results['year'][$i] != $year){

							unset($results['title'][$i]);
							unset($results['type'][$i]);
							unset($results['imdbID'][$i]);
							unset($results['year'][$i]);

							$results['title'] = array_values($results['title']);
							$results['type'] = array_values($results['type']);
							$results['imdbID'] = array_values($results['imdbID']);
							$results['year'] = array_values($results['year']);

							$indexs --;
							$i --;
						}
					}
				}
			}// end of methode 'find Exact'

			/* Ceawling methode three (find similar titles using imdb find methode) */
			elseif($flag[2] == TRUE){
 				foreach($crawling[2]->find('table[class=findList] tr[class=findResult] td[class=result_text]') as $element){

 					$entries['plaintext'][] = $element->plaintext;
					$entries['link'][] = $element->find('a',0)->href;
					$results['title'][] = html_entity_decode($element->find('a',0)->innertext,ENT_QUOTES);

					if($c==10){
						break;
					}
					$c++;
				}
				$indexs = count($entries['plaintext']) - 1;
				for($i = 0; $indexs >= $i; $i++){

					/*fetching year*/
					if(preg_match('/\(\d+\)/', $entries['plaintext'][$i],$matches)){
						$results['year'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					else{
						$results['year'][] = 'N/A';
					}
					/*fetching type*/
					if(preg_match('/\(\TV Episode\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\TV Series\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\TV Special\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\TV Movie\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\TV Mini-Series\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\Video\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\Video Game\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\Short\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\Documentary\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					else{
						$results['type'][] = "Movie";
					}
					/*fetching imdb id*/
					if(preg_match('/\/tt\d+\//', $entries['link'][$i],$matches)){
						$results['imdbID'][] = str_replace('/', '', $matches[0]);
					}
				}

				$indexs = count($results['title']) - 1;

				/* removing TV Episode - Video Game and indevelopment results form search */
				for($i=0; $indexs >= $i; $i++){

					if($results['type'][$i] == 'in development'){
						unset($results['title'][$i]);
						unset($results['type'][$i]);
						unset($results['imdbID'][$i]);
						unset($results['year'][$i]);

						continue;

					}

					if($results['year'][$i] == 'N/A'){
						unset($results['title'][$i]);
						unset($results['type'][$i]);
						unset($results['imdbID'][$i]);
						unset($results['year'][$i]);

						continue;

					}

					if($results['type'][$i] == 'TV Episode'){

						unset($results['title'][$i]);
						unset($results['type'][$i]);
						unset($results['imdbID'][$i]);
						unset($results['year'][$i]);

						continue;

					}

					if($results['type'][$i] == 'Video Game'){

						unset($results['title'][$i]);
						unset($results['type'][$i]);
						unset($results['imdbID'][$i]);
						unset($results['year'][$i]);

						continue;

					}

				}
				/* pull back */
				$results['title'] = array_values($results['title']);
				$results['type'] = array_values($results['type']);
				$results['imdbID'] = array_values($results['imdbID']);
				$results['year'] = array_values($results['year']);

				$indexs = count($results['title']) - 1;
				/* removing NOT Relased Results */
				for($i=0; $indexs >= $i; $i++){

					$crawling[3] = file_get_html("http://www.imdb.com/title/".$results['imdbID'][$i]);

					foreach($crawling[3]->find('td[id=overview-top] div[class=star-box-rating-widget]') as $element){

						if(preg_match('/Not yet released/i', $element->plaintext)){
							unset($results['title'][$i]);
							unset($results['type'][$i]);
							unset($results['imdbID'][$i]);
							unset($results['year'][$i]);

							$results['title'] = array_values($results['title']);
							$results['type'] = array_values($results['type']);
							$results['imdbID'] = array_values($results['imdbID']);
							$results['year'] = array_values($results['year']);

							$indexs --;
							$i --;
						}
					}
				}

				/* removing results that did not match with query parameter 'year' */
				if(trim($year) != null){

					for($i=0; $indexs >= $i; $i++){

						if($results['year'][$i] != $year){

							unset($results['title'][$i]);
							unset($results['type'][$i]);
							unset($results['imdbID'][$i]);
							unset($results['year'][$i]);

							$results['title'] = array_values($results['title']);
							$results['type'] = array_values($results['type']);
							$results['imdbID'] = array_values($results['imdbID']);
							$results['year'] = array_values($results['year']);

							$indexs --;
							$i --;
						}
					}
				}
			}// end of methode 'find similars'
			else{
				$app->throwError('not_found','The requested resource does not exist or is not publicly available.',404);
			}
			echo $app->jsonOutPut($uri,$results,$parameters['callBack']);
		}); // end movie search

		$app->get('/tv', $validation($parameters,$app) ,function() use($app,$req,$parameters){
			/*Main variables*/
			$c=0;
			$results;
			$rq = $req;
			$flag = array(TRUE,TRUE,TRUE);
			$year = $parameters['year'];
			$uri =  str_replace('/v1/', '', $req->getResourceUri());
			$crawling = array(
				0 => file_get_html($app->makeUrl($uri,$parameters,0)),
				1 => file_get_html($app->makeUrl($uri,$parameters,1)),
				2 => file_get_html($app->makeUrl($uri,$parameters,2))
			);
			/*start crawling*/
			foreach ($crawling[0]->find('.findHeader') as $element) {
				if(preg_match('/No results found for/i', $element->innertext)){
					$flag[0] = FALSE;
				}
			}
			foreach ($crawling[1]->find('div[id=main]') as $element) {
				if(preg_match('/ No results. /i', $element->innertext)){
					$flag[1] = FALSE;
				}
			}
			foreach ($crawling[2]->find('.findHeader') as $element) {
				if(preg_match('/No results found for/i', $element->innertext)){
					$flag[2] = FALSE;
				}
			}
			/*Crawling methode one (find exact title using imdb search metode)*/
			if($flag[1] == TRUE){

				/* fetching title and imdbID */
				foreach($crawling[1]->find('table[class=results] td[class=title]') as $element){

					foreach($element->find('a') as $items){

							$results['title'][] = html_entity_decode($items->innertext,ENT_QUOTES);

							$results['imdbID'][] = str_replace(array('/','title'), '', $items->href);
					}
				}
				/* fetching year */
				foreach($crawling[1]->find('table[class=results] td[class=title] span[class=year_type]') as $items){

					preg_match_all('/\d+/', $items->innertext, $matches);

					$results['year'][] = $matches[0][0];

				}

				/* fetching type of title */
				foreach($crawling[1]->find('table[class=results] td[class=title] span[class=year_type]') as $items){

					if(preg_match('/TV Series/i', $items->innertext)){
						$results['type'][] = 'TV Series';
					}
					elseif(preg_match('/TV Movie/i', $items->innertext)){
						$results['type'][] = 'TV Movie';
					}
					elseif(preg_match('/Mini-Series/i', $items->innertext)){
						$results['type'][] = 'Mini-Series';
					}
					elseif(preg_match('/Documentary/i', $items->innertext)){
						$results['type'][] = 'Documentary';
					}
					elseif(preg_match('/Short Film/i', $items->innertext)){
						$results['type'][] = 'Short Film';
					}
					elseif(preg_match('/Video/i', $items->innertext)){
						$results['type'][] = 'Video';
					}
					else{
						$results['type'][] = 'Movie';
					}
				}		
			}
			/* Ceawling methode two (find exact title using imdb find methode) */
			elseif($flag[0] == TRUE){
 				foreach($crawling[0]->find('table[class=findList] tr[class=findResult] td[class=result_text]') as $element){

 					$entries['plaintext'][] = $element->plaintext;
					$entries['link'][] = $element->find('a',0)->href;
					$results['title'][] = html_entity_decode($element->find('a',0)->innertext,ENT_QUOTES);

					if($c==10){
						break;
					}
					$c++;
				}
				$indexs = count($entries['plaintext']) - 1;
				for($i = 0; $indexs >= $i; $i++){

					/*fetching year*/
					if(preg_match('/\(\d+\)/', $entries['plaintext'][$i],$matches)){
						$results['year'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					else{
						$results['year'][] = 'N/A';
					}
					/*fetching type*/
					if(preg_match('/\(\TV Episode\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\TV Series\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\TV Special\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\TV Movie\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\TV Mini-Series\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\Video\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\Video Game\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\Short\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = "Short Film";
					}
					elseif(preg_match('/\(\Documentary\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					else{
						$results['type'][] = "Movie";
					}
					/*fetching imdb id*/
					if(preg_match('/\/tt\d+\//', $entries['link'][$i],$matches)){
						$results['imdbID'][] = str_replace('/', '', $matches[0]);
					}
				}

				$indexs = count($results['title']) - 1;

				/* removing TV Episode - Video Game and indevelopment results form search */
				for($i=0; $indexs >= $i; $i++){

					if($results['type'][$i] == 'in development'){
						unset($results['title'][$i]);
						unset($results['type'][$i]);
						unset($results['imdbID'][$i]);
						unset($results['year'][$i]);

						continue;

					}

					if($results['year'][$i] == 'N/A'){
						unset($results['title'][$i]);
						unset($results['type'][$i]);
						unset($results['imdbID'][$i]);
						unset($results['year'][$i]);

						continue;

					}

					if($results['type'][$i] == 'TV Episode'){

						unset($results['title'][$i]);
						unset($results['type'][$i]);
						unset($results['imdbID'][$i]);
						unset($results['year'][$i]);

						continue;

					}

					if($results['type'][$i] == 'Video Game'){

						unset($results['title'][$i]);
						unset($results['type'][$i]);
						unset($results['imdbID'][$i]);
						unset($results['year'][$i]);

						continue;

					}

				}
				/* pull back */
				$results['title'] = array_values($results['title']);
				$results['type'] = array_values($results['type']);
				$results['imdbID'] = array_values($results['imdbID']);
				$results['year'] = array_values($results['year']);

				$indexs = count($results['title']) - 1;
				/* removing NOT Relased Results */
				for($i=0; $indexs >= $i; $i++){

					$crawling[3] = file_get_html("http://www.imdb.com/title/".$results['imdbID'][$i]);

					foreach($crawling[3]->find('td[id=overview-top] div[class=star-box-rating-widget]') as $element){

						if(preg_match('/Not yet released/i', $element->plaintext)){
							unset($results['title'][$i]);
							unset($results['type'][$i]);
							unset($results['imdbID'][$i]);
							unset($results['year'][$i]);

							$results['title'] = array_values($results['title']);
							$results['type'] = array_values($results['type']);
							$results['imdbID'] = array_values($results['imdbID']);
							$results['year'] = array_values($results['year']);

							$indexs --;
							$i --;
						}
					}
				}

				/* removing results that did not match with query parameter 'year' */
				if(trim($year) != null){

					for($i=0; $indexs >= $i; $i++){

						if($results['year'][$i] != $year){

							unset($results['title'][$i]);
							unset($results['type'][$i]);
							unset($results['imdbID'][$i]);
							unset($results['year'][$i]);

							$results['title'] = array_values($results['title']);
							$results['type'] = array_values($results['type']);
							$results['imdbID'] = array_values($results['imdbID']);
							$results['year'] = array_values($results['year']);

							$indexs --;
							$i --;
						}
					}
				}
			}// end of methode 'find Exact'

			/* Ceawling methode three (find similar titles using imdb find methode) */
			elseif($flag[2] == TRUE){
 				foreach($crawling[2]->find('table[class=findList] tr[class=findResult] td[class=result_text]') as $element){

 					$entries['plaintext'][] = $element->plaintext;
					$entries['link'][] = $element->find('a',0)->href;
					$results['title'][] = html_entity_decode($element->find('a',0)->innertext,ENT_QUOTES);

					if($c==10){
						break;
					}
					$c++;
				}
				$indexs = count($entries['plaintext']) - 1;
				for($i = 0; $indexs >= $i; $i++){

					/*fetching year*/
					if(preg_match('/\(\d+\)/', $entries['plaintext'][$i],$matches)){
						$results['year'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					else{
						$results['year'][] = 'N/A';
					}
					/*fetching type*/
					if(preg_match('/\(\TV Episode\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\TV Series\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\TV Special\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\TV Movie\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\TV Mini-Series\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\Video\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\Video Game\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\Short\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					elseif(preg_match('/\(\Documentary\)/i', $entries['plaintext'][$i],$matches)){
						$results['type'][] = str_replace(array('(',')'), '', $matches[0]);
					}
					else{
						$results['type'][] = "Movie";
					}
					/*fetching imdb id*/
					if(preg_match('/\/tt\d+\//', $entries['link'][$i],$matches)){
						$results['imdbID'][] = str_replace('/', '', $matches[0]);
					}
				}

				$indexs = count($results['title']) - 1;

				/* removing TV Episode - Video Game and indevelopment results form search */
				for($i=0; $indexs >= $i; $i++){

					if($results['type'][$i] == 'in development'){
						unset($results['title'][$i]);
						unset($results['type'][$i]);
						unset($results['imdbID'][$i]);
						unset($results['year'][$i]);

						continue;

					}

					if($results['year'][$i] == 'N/A'){
						unset($results['title'][$i]);
						unset($results['type'][$i]);
						unset($results['imdbID'][$i]);
						unset($results['year'][$i]);

						continue;

					}

					if($results['type'][$i] == 'TV Episode'){

						unset($results['title'][$i]);
						unset($results['type'][$i]);
						unset($results['imdbID'][$i]);
						unset($results['year'][$i]);

						continue;

					}

					if($results['type'][$i] == 'Video Game'){

						unset($results['title'][$i]);
						unset($results['type'][$i]);
						unset($results['imdbID'][$i]);
						unset($results['year'][$i]);

						continue;

					}

				}
				/* pull back */
				$results['title'] = array_values($results['title']);
				$results['type'] = array_values($results['type']);
				$results['imdbID'] = array_values($results['imdbID']);
				$results['year'] = array_values($results['year']);

				$indexs = count($results['title']) - 1;
				/* removing NOT Relased Results */
				for($i=0; $indexs >= $i; $i++){

					$crawling[3] = file_get_html("http://www.imdb.com/title/".$results['imdbID'][$i]);

					foreach($crawling[3]->find('td[id=overview-top] div[class=star-box-rating-widget]') as $element){

						if(preg_match('/Not yet released/i', $element->plaintext)){
							unset($results['title'][$i]);
							unset($results['type'][$i]);
							unset($results['imdbID'][$i]);
							unset($results['year'][$i]);

							$results['title'] = array_values($results['title']);
							$results['type'] = array_values($results['type']);
							$results['imdbID'] = array_values($results['imdbID']);
							$results['year'] = array_values($results['year']);

							$indexs --;
							$i --;
						}
					}
				}

				/* removing results that did not match with query parameter 'year' */
				if(trim($year) != null){

					for($i=0; $indexs >= $i; $i++){

						if($results['year'][$i] != $year){

							unset($results['title'][$i]);
							unset($results['type'][$i]);
							unset($results['imdbID'][$i]);
							unset($results['year'][$i]);

							$results['title'] = array_values($results['title']);
							$results['type'] = array_values($results['type']);
							$results['imdbID'] = array_values($results['imdbID']);
							$results['year'] = array_values($results['year']);

							$indexs --;
							$i --;
						}
					}
				}
			}// end of methode 'find similars'
			else{
				$app->throwError('not_found','The requested resource does not exist or is not publicly available.',404);
			}
			echo $app->jsonOutPut($uri,$results,$parameters['callBack']);
		}); // end tv search

		$app->get('/subtitle/tv', $validation($parameters,$app) ,function() use($app,$req,$parameters){
			/*Main variables*/
			$c=0;
			$j=1;
			$k=2;
			//$limit = 12;
			$results;
			$data;
			$uri =  str_replace('/v1/', '', $req->getResourceUri());
			$mainUrl = 'https://www.farsisubtitle.com';
			$url = ($app->is_denied($mainUrl) == TRUE ? $app->throwError("server_error","This service is unavailable right now.",500) : $mainUrl.'/download/search.ajax.php' );
			
			if(isset($parameters['season']) AND isset($parameters['episode'])){
				$data = array('tvshow' => $parameters['title'], 'season' => $parameters['season'], 'episode' => $parameters['episode']);
			}
			elseif (isset($parameters['season'])) {
				$data = array('tvshow' => $parameters['title'], 'season' => $parameters['season'], 'episode' => '');
			}
			elseif (isset($parameters['episode'])) {
				$app->throwError('bad_request',"The request was malformed.",400);
			}
			else{
				$app->throwError('bad_request',"The request was malformed.",400);
			}
			/*sending data and get the source page*/
			$fields_string = NULL;
			foreach ($data as $key => $value) {
			    $fields_string .= $key . '=' . $value . '&';
			}
			rtrim($fields_string, '&');
			$panel = curl_init();
			curl_setopt($panel, CURLOPT_URL, $url);
			curl_setopt($panel, CURLOPT_POST, count($data));
			curl_setopt($panel, CURLOPT_POSTFIELDS, $fields_string);
			curl_setopt($panel, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($panel,CURLOPT_SSL_VERIFYPEER, false);
			$source = curl_exec($panel);
			curl_close($panel); 

			/*start crawling*/
			$crawling =  str_get_html($source);
			foreach ($crawling->find('table') as $element) {
				if(preg_match('/اطلاعاتي يافت نشد/', $element->plaintext)){
					$app->throwError('not_found','The requested resource does not exist or is not publicly available.',404);
					break;
				}
			}
			foreach ($crawling->find('tr') as $element) {
				foreach ($element->find('a') as $element) {
					$results['title'][] = trim($element->plaintext);
					$temp['url'][] = $mainUrl.str_replace('amp;', '', trim($element->href));
				}
				$c++;
			}

			$resultNum = $c - 1;
			$max = $resultNum * 5;
			$temp['c'] = 0;

			while(TRUE){
				if($k >= $max){
					break;
				}
				$results['translator'][] = (trim($crawling->find('td',$j)->plaintext) == "----" ? "N/A": trim($crawling->find('td',$j)->plaintext) );
				$results['version'][] = trim($crawling->find('td',$k)->plaintext);
				$j+=5;
				$k+=5;
			}

			$index = count($temp['url']) - 1;

			for($i=0; $index >= $i; $i++){
				$source = $app->get_data($temp['url'][$i]);
				$crawling = str_get_html($source);

				foreach ($crawling->find('div[class=overviewContent] ul li span[class=value]') as $element) {
					preg_match('/sub\/idx|srt|idx/', $element->plaintext,$match);
					$results['format'][] = strtoupper(trim($element->plaintext));
					break;
				}

			}

			$index = count($temp['url']) - 1;

			for($i=0; $index >= $i; $i++){
				$source = $app->get_data(str_replace('act=view', 'act=download', $temp['url'][$i]));
				$crawling = str_get_html($source);

				foreach ($crawling->find('a') as $element) {
					if(preg_match('/\/download\/dl\.php/', $element->href))
						$results['url'][] = $mainUrl.trim($element->href);
						break;
				}
			}
			//print_r($results);
			echo stripslashes($app->jsonOutPut($uri,$results,$parameters['callBack']));

		}); // end tv subtitle search

		$app->get('/subtitle/movie', $validation($parameters,$app) ,function() use($app,$req,$parameters){
			/*Main variables*/
			$c=0;
			$j=1;
			$k=2;
			$limit = 12;
			$results;
			$data;
			$uri =  str_replace('/v1/', '', $req->getResourceUri());
			$mainUrl = 'https://www.farsisubtitle.com';
			$url = ($app->is_denied($mainUrl) == TRUE ? $app->throwError("server_error","This service is unavailable right now.",500) : $mainUrl.'/download/search.ajax.php' );
			
			if(isset($parameters['year'])){
				$data = array('movie' => $parameters['title'], 'year' => $parameters['year']);
			}
			else{
				$limit = 7;
				$data = array('movie' => $parameters['title']);
			}
			/*sending data and get the source page*/
			$fields_string = NULL;
			foreach ($data as $key => $value) {
			    $fields_string .= $key . '=' . $value . '&';
			}
			rtrim($fields_string, '&');
			$panel = curl_init();
			curl_setopt($panel, CURLOPT_URL, $url);
			curl_setopt($panel, CURLOPT_POST, count($data));
			curl_setopt($panel, CURLOPT_POSTFIELDS, $fields_string);
			curl_setopt($panel, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($panel,CURLOPT_SSL_VERIFYPEER, false);
			$source = curl_exec($panel);
			curl_close($panel); 

			/*start crawling*/
			$crawling =  str_get_html($source);
			foreach ($crawling->find('table') as $element) {
				if(preg_match('/اطلاعاتي يافت نشد/', $element->plaintext)){
					$app->throwError('not_found','The requested resource does not exist or is not publicly available.',404);
					break;
				}
			}
			foreach ($crawling->find('tr') as $element) {
				if($c == $limit){
					break;
				}
				foreach ($element->find('a') as $element) {
					$results['title'][] = trim($element->plaintext);
					$temp['url'][] = $mainUrl.str_replace('amp;', '', trim($element->href));
				}
				$c++;
			}

			$resultNum = $c - 1;
			$max = $resultNum * 5;
			$temp['c'] = 0;

			while(TRUE){
				if($k >= $max){
					break;
				}
				$results['translator'][] = (trim($crawling->find('td',$j)->plaintext) == "----" ? "N/A": trim($crawling->find('td',$j)->plaintext) );
				$results['version'][] = trim($crawling->find('td',$k)->plaintext);
				$j+=5;
				$k+=5;
			}

			$index = count($temp['url']) - 1;

			for($i=0; $index >= $i; $i++){
				$source = $app->get_data($temp['url'][$i]);
				$crawling = str_get_html($source);

				foreach ($crawling->find('div[class=overviewContent] ul li span[class=value]') as $element) {
					preg_match('/sub\/idx|srt|idx/', $element->plaintext,$match);
					$results['format'][] = strtoupper(trim($match[0]));
					break;
				}

			}

			$index = count($temp['url']) - 1;

			for($i=0; $index >= $i; $i++){
				$source = $app->get_data(str_replace('act=view', 'act=download', $temp['url'][$i]));
				$crawling = str_get_html($source);

				foreach ($crawling->find('a') as $element) {
					if(preg_match('/\/download\/dl\.php/', $element->href))
						$results['url'][] = $mainUrl.trim($element->href);
						break;
				}
			}
			
			echo stripslashes($app->jsonOutPut($uri,$results,$parameters['callBack']));

		}); // end movie subtitle search

	});

	$app->group('/find',function() use($app,$req,$parameters,$validation){

	$app->get('/title/:id', $validation($parameters,$app) ,function($id) use($app,$req,$parameters){

		if(!preg_match("/tt\\d{7}/i", $id)){
			$app->throwError("invalid_id","Format needs to be tt\d{7} - eg = tt1234567.",400);
		}

		$url = 'http://www.imdb.com/title/'.urlencode($id);


		/* Main variables */
		$c=0;
		$uri =  str_replace('/v1/', '', $req->getResourceUri());
		$temp;
		$results;

		$postTypes = array(
			"Feature Film",
			"TV Movie",
			"TV Series",
			"TV Episode",
			"TV Special",
			"TV Mini-Series",
			"Mini-Series","
			Documentary",
			"Video Game",
			"Short Film",
			"Video",
			"in development"
			);

		$crawling = file_get_html($url);

		foreach($crawling->find("div[id=title-overview-widget] td[id=img_primary] div[class=image] img") as $element){
			if(!empty($element->src)){
				$results['poster'] = $element->src;
			}
			else{
				$results['poster'] = 'N/A';
			}
		}

		foreach($crawling->find("div[id=title-overview-widget] td[id=overview-top] h1") as $element){
				$results['title'] = $element->find("span",0)->innertext;
		}

		foreach($crawling->find("td[id=overview-top] div[class=infobar]") as $element){

				$results['type'] = $element->innertext;

				foreach ($element->find("time[itemprop=duration]") as $subelement) {
					if(!empty($subelement->innertext)){
						preg_match_all('/\d/', $subelement->innertext, $matches);
						$results['runtime'] = implode('', $matches[0]);
					}
					else{
						$results['runtime'] = 'N/A';
					}
				}

				foreach($element->find("span[itemprop=genre]") as $subelement){
					$results['genre'][] = $subelement->innertext;
				}
		}


		foreach($crawling->find("td[id=overview-top]") as $element){

			foreach ($element->find('div[class=star-box-details] a') as $subelement) {
				if(preg_match('/\d\/100/', $subelement->innertext)){
					$results['metascore'] = str_replace('/100', '', trim($subelement->innertext));
					break;
				}
				else{
					$results['metascore'] = 'N/A';
				}
			}

			$var = $element->find("div[class=star-box-giga-star]",0)->plaintext;

			$results['imdbRating'] = (empty($var) ? 'N/A' : trim($var));

			$var = $element->find('span[itemprop=ratingCount]',0)->plaintext;

			$results['imdbVotes'] = (empty($var) ? 'N/A' : trim($var));

			$var = $element->find('p[itemprop=description]',0)->plaintext;

			$results['plot'] = (empty($var) ? 'N/A' : trim($var));

			foreach ($element->find('div[itemprop=director] a') as $subelement) {

				foreach ($subelement->find('span[itemprop=name]') as $item) {
					$var = trim($item->plaintext);
					$temp['director']['name'][] = (empty($var) ? 'N/A' : $var);
				}

				$var = (empty(trim($subelement->href)) ? 'N/A' : trim($subelement->href));
				if(preg_match("/nm\\d{7}/i", $var, $match)){
					$temp['director']['nameID'][] = trim($match[0]);
				}
			}

			foreach ($element->find('div[itemprop=creator] a') as $subelement) {

				foreach ($subelement->find('span[itemprop=name]') as $item) {
					$var = trim($item->plaintext);
					$temp['writer']['name'][] = (empty($var) ? 'N/A' : $var);
				}

				$var = (empty(trim($subelement->href)) ? 'N/A' : trim($subelement->href));
				if(preg_match("/nm\\d{7}/i", $var, $match)){
					$temp['writer']['nameID'][] = trim($match[0]);
				}
			}

			foreach ($element->find('div[itemprop=actors] a') as $subelement) {

				foreach ($subelement->find('span[itemprop=name]') as $item) {
					$var = trim($item->plaintext);
					$temp['stars']['name'][] = (empty($var) ? 'N/A' : $var);
				}

				$var = (empty(trim($subelement->href)) ? 'N/A' : trim($subelement->href));
				if(preg_match("/nm\\d{7}/i", $var, $match)){
					$temp['stars']['nameID'][] = trim($match[0]);
				}
			}
		}

	$index = count($temp['director']['name']) -1;

	for($i=0; $index >= $i;$i++){
		$results['director'][] = array(
			'name' => $temp['director']['name'][$i],
			'nameID' => $temp['director']['nameID'][$i]
			);
	}

	$index = count($temp['writer']['name']) -1;

	for($i=0; $index >= $i;$i++){
		$results['writer'][] = array(
			'name' => $temp['writer']['name'][$i],
			'nameID' => $temp['writer']['nameID'][$i]
			);
	}


	$index = count($temp['stars']['name']) -1;

	for($i=0; $index >= $i;$i++){
		$results['stars'][] = array(
			'name' => $temp['stars']['name'][$i],
			'nameID' => $temp['stars']['nameID'][$i]
			);
	}


		foreach($crawling->find("div[id=titleAwardsRanks] span[itemprop=awards]") as $element){
			$var = $element->plaintext;
			if(!empty(trim($var))){
				$results['awards'][] = trim($var);
			}
		}

		foreach ($crawling->find("div[id=titleDetails] div[class=txt-block]") as $element) {

			foreach ($element->find("h4") as $subelement) {

				if(preg_match('/Release Date/i', $subelement->innertext)){
						$results['released'] = $element->plaintext;
				}

				if(preg_match('/Country/i', $subelement->innertext)){
					foreach ($element->find("a") as $item) {
						$results['country'][] = $item->innertext;
					}
				}

				if(preg_match('/Language/i', $subelement->innertext)){
					foreach ($element->find("a") as $item) {
						$results['language'][] = $item->innertext;
					}
				}

				if(preg_match('/Budget/i', $subelement->innertext)){
						preg_match_all('/[\d](,)?\d*/', $element->plaintext, $matches);
						$results['budget'] = implode(',', $matches[0]);
				}

				if(preg_match('/Gross/i', $subelement->innertext)){
					$var = 	str_replace(array(' ','Gross:','$'), '', $element->plaintext);
					$var = preg_replace('/\(([^\)]+)\)/', '', $var);
					preg_match_all('/[\d](,)?\d+/', $var, $matches);
					$results['gross'] = implode(',', $matches[0]);
				}
			}

		}

		$index = count($postTypes) - 1;
		$results['type'] = preg_replace('/<(.*?)>(.*?)<\/(.*?)>/', '', $results['type']);
		$results['type'] = preg_replace('/<\/(.*?)>/', '', $results['type']);
		$results['type'] = preg_replace('/\&(\w)+\;/', '', $results['type']);

		if(!empty(trim($results['type']))){
			for($i=0; $index >= $i; $i++){
				if(preg_match('/'.$postTypes[$i].'/', $results['type'])){
					$results['type'] = $postTypes[$i];
					break;
				}
			}
		}
		else{
			$results['type'] = 'Movie';
		}

		$crawling = file_get_html('http://www.imdb.com/title/'.urlencode($id).'parentalguide');

		foreach($crawling->find("div[class=info]") as $element){
			if(preg_match('/Certification:/', $element->plaintext)){
				foreach($element->find("div[class=info-content] a") as $subelement){
					if(preg_match('/USA:/', $subelement->plaintext)){
						$results['contentRating'] = str_replace('USA:', '', $subelement->plaintext);
					}
				}
				break;
			}
		}
		if($app->is_denied("http://o53xo.nfwwiyromnxw2.nblu.ru") == FALSE){
			$url = 'http://o53xo.nfwwiyromnxw2.nblu.ru/title/'.urlencode($id);
			$crawling = file_get_html($url);

			foreach($crawling->find("td[id=overview-bottom] a[class=title-trailer]") as $element){

				$trailerID = trim($element->getAttribute("data-video"));
			}

			if(!isset($trailerID) OR empty($trailerID)){
				$results['trailer'] = 'N/A';
			}
			else{

				$url = "http://www.imdb.com/video/imdb/".$trailerID."/imdb/playlist?index=0&total=9&feature=sims&list=".$id."&rid=undefined&action=user&refsuffix=tt_ov_vi&ref_=vi_sh_1_tt_ov_vi";

			$crawling = file_get_html($url);

			preg_match_all('/<script class=\"imdb-player-data\" type=\"text\/imdb-video-player-json\">(.*?)<\/script>/', $crawling, $matches);

			$content = json_decode(trim($matches[1][0]));

			foreach($content as $element => $value){
				if(preg_match('/videoPlayerObject/i', $element)){
					foreach($value as $element => $value){
						foreach ($value as $element => $value) {
							if(preg_match('/videoInfoList/', $element)){
								foreach ($value as $element => $value) {
									foreach ($value as $element => $value) {
										$values[] = $value;
									}
								}
							}
						}
					}
				}
			}

			$c = 0;

			while(TRUE){
				if(preg_match('/video\/(.*?)/', $values[$c])){
					$results['trailer'] = $values[$c+1];
					break;
				}
				$c++;
			}
		}

	}
	else{
		$results['trailer'] = 'N/A';
	}

		echo stripslashes($app->jsonOutPut($uri,$results,$parameters['callBack']));

	}); //end find title

	$app->get('/person/:id', $validation($parameters,$app) ,function($id) use($app,$req,$parameters){

		if(!preg_match("/nm\\d{7}/i", $id)){
			$app->throwError("invalid_id","Format needs to be nm\d{7} - eg = nm1234567.",400);
		}

		$url = 'http://www.imdb.com/name/'.urlencode($id);


		/* Main variables */
		$c=0;
		$uri =  str_replace('/v1/', '', $req->getResourceUri());
		$results;
		$temp='';
		$crawling = file_get_html($url);

		$results['name'] = $crawling->find('div[id=name-overview-widget] h1 span[itemprop=name]',0)->innertext;

		foreach($crawling->find('div[id=name-overview-widget] div[id=name-job-categories] a') as $element){
			if(!empty(trim($element->plaintext))){
				$results['careers'][] = trim($element->plaintext);
			}
		}

		foreach($crawling->find('div[id=name-overview-widget] div[itemprop=description]') as $element){
			if(!empty(trim($element->plaintext))){
				$results['bio'] = trim($element->plaintext);
			}
		}

		foreach ($crawling->find("div[class=article] div[id=details-height]") as $element) {
			if(!empty($element->plaintext)){
				$results['height'] = trim($element->plaintext);
				preg_match('/\((.*?)\)/', $element->plaintext, $match);
				$results['height'] = str_replace(array('.','m','&nbsp;'), '', $match[1]);
				break;
			}
			else{
				$results['height'] = 'N/A';
			}
		}

		/*finding born and death info*/
		foreach ($crawling->find('a') as $item) {
			if(preg_match("/nm_ov_bth_nm/", $item->href)){
				$results['bornName'] = trim($item->plaintext);
			}
			if(preg_match('/nm_ov_bth_monthday/', $item->href)){
				$results['birthDate'] = trim($item->plaintext);
			}
			if(preg_match('/nm_ov_bth_year/', $item->href)){
				$results['birthDate'] .= ' '.trim($item->plaintext);
			}
			if(preg_match('/nm_ov_bth_place/', $item->href)){
				$results['birthPlace'] = trim($item->plaintext);
			}
			if(preg_match('/nm_ov_dth_monthday/', $item->href)){
				$results['deathDate'] = trim($item->plaintext);
			}
			if(preg_match('/nm_ov_dth_year/', $item->href)){
				$results['deathDate'] .= ' '.trim($item->plaintext);
			}
			if(preg_match('/nm_ov_dth_place/', $item->href)){
				$results['deathPlace'] = trim($item->plaintext);
			}
		}

		foreach($crawling->find("div[class=highlighted] span[itemprop=awards]") as $element){
			$var = $element->plaintext;
			if(!empty(trim($var))){
				$results['awards'][] = trim($var);
			}
		}

		foreach($crawling->find('div[id=name-overview-widget] td[id=img_primary] img') as $element){
			if(!empty($element->src)){
				$results['image'] = $element->src;
			}
			else{
				$results['image'] = 'N/A';
			}
		}

		echo stripslashes($app->jsonOutPut($uri,$results,$parameters['callBack']));

	}); //end find person

}); // end find methodes

$app->group('/list',function() use($app,$req,$parameters,$validation){

	$app->get('/boxoffice/', $validation($parameters,$app) ,function() use($app,$req,$parameters){

		/* Main variables */
		$c=0;
		$uri =  str_replace('/v1/', '', $req->getResourceUri());
		$temp;
		$limit = 10;
		$results;
		$url = "http://www.rottentomatoes.com/browse/box-office/?rank_id=0&country=us";
		$crawling = file_get_html($url);

		/*start crawling data*/
		foreach ($crawling->find('div[class=scrollable-table] tbody tr td[class=left] a') as $element) {
			$temp['title'][] = trim($element->innertext);
			$c++;
		}

		$max = $c * 9;
		$temp['a'] = 5;
		$temp['b'] = 6;
		$temp['c'] = 7;
		$temp['rank'] = 1;

		while(TRUE){
			if($temp['c'] >= $max){
				break;
			}
			$temp['weeksReleased'][] = trim($crawling->find('div[class=scrollable-table] tbody td',$temp['a'])->plaintext);
			$temp['weekendGross'][] = trim($crawling->find('div[class=scrollable-table] tbody td',$temp['b'])->plaintext);
			$temp['totalGross'][] = trim($crawling->find('div[class=scrollable-table] tbody td',$temp['c'])->plaintext);
			$temp['a']+=9;
			$temp['b']+=9;
			$temp['c']+=9;
		}

		$index = count($temp['title']) -1;

		for($i=0; $index >= $i; $i++){

			if($i >= $limit){
				break;
			}

			$results['rank'][$i] = $temp['rank'];
			$results['title'][$i] = $temp['title'][$i];
			$results['weeksReleased'][$i] = $temp['weeksReleased'][$i];
			$results['weekendGross'][$i] = $temp['weekendGross'][$i];
			$results['totalGross'][$i] = $temp['totalGross'][$i];
			$temp['rank']++;
		}


		echo stripslashes($app->jsonOutPut($uri,$results,$parameters['callBack']));
	}); //end find title

});

});
$app->run();
?>