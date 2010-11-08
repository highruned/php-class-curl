# php-class-curl
This is a basic class to easily manage curl connections. 

libcurl comes with multi-threading built in, which is called multicurl. This class also provides that functionality as an option.

You send a request object to the manager, which sychronously return a result, or asychronously push the object into a request pool firing off a callback later.

The request object comes with handy shortcuts such as default options, setting url, referer, timeout, etc
The response object comes with handy shortcuts such as the http status code, the header list, and of course the data.

## License
`php-class-curl` is released under the MIT license. A copy of the MIT license can be found in the `LICENSE` file.
