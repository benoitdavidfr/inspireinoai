# Specification in OAI of Inspire network services

Specification of Inspire network services using the [Open API initiative](https://www.openapis.org/) mechanism.

## Why ?

More and more public geographic datasets are published using open data platforms when they should use an Inspire platform.
This can be explained by a lack of knowledge of Inspire obligations by some public authorities, but also by the complexity of the current technical framework.
In France, beyond traditional applications such as spatial data portals, the Inspire data catalogs are not sufficiently used by professional or consumer applications.
Thus, when an authority makes the effort to publish its data in this infrastructure, they are insufficiently valued. Again, the complexity of the technical framework, especially for Web developers that are not GIS-experts, slow down the development of applications using the infrastructure.
Therefore, in practice, Inspire competes with the Open Data as the objectives of both actions are close and should be more complementary than competitive.
  Making this statement, this reflection is to provide a data publishing technical framework that :
  * simplifies publishing datasets for public authorities, a priori, not familiar with the current technical framework of Inspire and
  * facilitates the use of the infrastructure by Web programmers that are not GIS-experts

## The basic ideas

* Data are managed in web applications and one shouldn't ask the user to write metadata, to publish them in a catalog
  and organize the links with discovery/view/download services.
  The web application (that I will call **thematic application**) that manages the data should also manage all the needed
  information and expose it on the web.
  Therefore the web application should be an Inspire download service
  and, because it knows the datasets, should also be able to expose the metadata of the datasets.
* Some applications are able to draw maps and therefore can also easily be a view service.
  Others are not. If somebody defines how to style the information, it seems possible to develop a generic mapping
  application that draws maps using this styling information to draw data downloaded from a thematic application.
* A catalogue (also known as discovery service) is usefull to find information from the different thematic applications.
  Because the thematic applications already know the metadata, adding a new dataset or service to a catalogue shoud be
  simple, the only thing to do is to push them from the application to the catalogue.

## The technical solution

Technically, the solution is to add to a web application an API interface that implements at least a download service
and eventuelly a view service.
If the thematic application doesn't implement a view service, a mapping application will be used to make a view service
by using the data from the download service.
The catalogue can be generally a specific application distinct from thematic applications.

Defining the API specifications using the OAI mechanism contribute to standardize the API
and simplifies the implementation for thematic applications.

A [preliminary version of OAI/Swagger definition for download and view services is available here](https://app.swaggerhub.com/apis/benoitdavidfr/inspireinoai).
The source file corresponds to
[inspireinoai.yaml](https://raw.githubusercontent.com/benoitdavidfr/inspireinoai/master/inspireinoai.yaml).

A [preliminary version of OAI/Swagger definition for a discovery service is available here](https://app.swaggerhub.com/apis/benoitdavidfr/discoveryinoai).
The source file corresponds to
[discoveryinoai.yaml](https://raw.githubusercontent.com/benoitdavidfr/inspireinoai/master/discoveryinoai.yaml).

These specifications are designed so that the thematic application implmenting them will be conformant
to the Inspire regulations.

These specifications can be used in several ways:
  * natively implement API to make a thematic application compliant to Inspire,
  * build a bridge between the OAI world and the OG/ISO191xx world, in one way or the other.

## References

* [Directive 2007/2/EC of the European Parliament and of the Council of 14 March 2007 establishing an Infrastructure
  for Spatial Information in the European Community (INSPIRE)](http://data.europa.eu/eli/dir/2007/2/oj)
* [Commission Regulation (EC) No 976/2009 of 19 October 2009 implementing Directive 2007/2/EC of the European
  Parliament and of the Council as regards the Network Services ](http://data.europa.eu/eli/reg/2009/976/2014-12-31)
* [Commission Regulation (EC) No 1205/2008 of 3 December 2008 implementing Directive 2007/2/EC of the European
  Parliament and of the Council as regards metadata](http://data.europa.eu/eli/reg/2008/1205/oj)
* [OpenAPI Initiative (OAI)](https://www.openapis.org/)
* [OpenAPI Specification Version 2.0 (fka Swagger RESTful API Documentation 
  Specification)](https://github.com/OAI/OpenAPI-Specification/blob/master/versions/2.0.md)
* [The GeoJSON Format, RFC 7946, August 2016](https://tools.ietf.org/html/rfc7946)
* [Language codes, ISO 639-2 alpha-3](https://fr.wikipedia.org/wiki/Liste_des_codes_ISO_639-2)
  