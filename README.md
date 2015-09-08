# Popcorn API System
A simple API system to retrieve movie/series information, all content, images and trailers.

Requirements
---------------------
 * PHP >= 5.3
 * Reliable connection between server and IMDB

Used libraries
---------------------
*   [Slim Framework](http://www.slimframework.com/)
*   JSON middleware for Slim
*   [PHP Simple HTML DOM Parser](http://simplehtmldom.sourceforge.net/)

Features
---------------------
* Retrive movie/series information based on “Best Effort” (including related poster and trailer) from [IMDB](http://www.imdb.com/).
* Retrive subtitles (only in Persian language) from [Farsi subtitle](https://www.farsisubtitle.com/).
* Retrive boxoffice information from [Rotten Tomatoes](http://www.rottentomatoes.com/).
* JSON & JSONP response format.
 
Installation
---------------------
You can clone this repository staright from GitHub or download repository archive form [here](https://github.com/iazami/popcorn/archive/master.zip)

**Note:** you may need to uncomment `RewriteBase /` (line 7) in `.httaccsess` file.

Documentation
---------------------
We've documented API endpoints of Popcorn below.

**Note:** Search results doesn't include in-development, tv episode and video-game title type.

### GET v1/search/multi
Search the movie, tv series collections.

Required Parameters: `title`

Optional Parameters: `year`, `callback`

### GET v1/search/movie
Search the movie collections (including: feature film, documentary, short film and video).

Required Parameters: `title`

Optional Parameters: `year`, `callback`

### GET v1/search/tv
Search the tv collections (including: tv movie, tv series and mini series).

Required Parameters: `title`

Optional Parameters: `year`, `callback`

### GET v1/search/subtitle/movie
Search the movie subtitle information based on title (The results are not very accurate).

Required Parameters: `title`

Optional Parameters: `year`, `callback`

### GET v1/search/subtitle/tv
Search the series subtitle information based on title (The results are not very accurate).

Required Parameters: `title`

Optional Parameters: `season`, `episode`, `callback`

### GET v1/find/title/{imdbID}
retrieve exact information of title types(such as movie, tv series etc...) based on IMDB ID (eg: tt1480055)

Required Parameters: `id`

Optional Parameters: `callback`

### GET v1/find/person/{imdbID}
retrieve exact information of specific person based on IMDB ID (eg: nm1125275)

Required Parameters: `id`

Optional Parameters: `callback`

### GET v1/list/boxoffice
retrieve top 10 titles in U.S boxoffice.

Optional Parameters: `callback`

Final notes
---------------------
Because of Internet restrictions in Iran, trailers servers are restricted. We had to create a function for checking out that for Iranian server. (Check out `is_denied` function in `/lib/API/application.php, line:341`)

License
---------------------
This software is released under the MIT License.

> Copyright © 2015 MohammadHadi Azami <iazami@outlook.com>

> Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the “Software”), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

> The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

> THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
