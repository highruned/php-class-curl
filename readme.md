# php-class-curl
Basic classes to easily manage curl connections. Please see the `examples` directory. 

libcurl comes with multi-threading built in, which is called multicurl. This class also provides that functionality as an option.  

You send a request object to the manager, which sychronously return a result, or asychronously push the object into a request pool firing off a callback later.  

The request object comes with handy shortcuts such as default options, setting url, referer, timeout, etc.  
The response object comes with handy shortcuts such as the http status code, the header list, and of course the data.  

## Classes

### curl_request

Used to setup a cURL request. Helper methods. Can also specify standard cURL parameters.

### curl_response

Helpful methods and commonly needed data.

### curl

Service which processed requests and returns responses.  

