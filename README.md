# OAI Specifications for Inspire network services

Specification of Inspire network services using the [Open API initiative (OAI)](https://www.openapis.org/) formalism.

## Why ?

More and more public geographic datasets are published using open data platforms when they should use an Inspire platform.
This can be explained by a lack of knowledge of Inspire obligations by some public authorities, but also by the complexity of the current Inspire technical framework.
In France, beyond traditional applications such as spatial data portals, the Inspire data catalogs are not sufficiently used by professional or consumer applications.
Thus, when an authority makes the effort to publish its data in this infrastructure, they are insufficiently valued. Again, the complexity of the technical framework, especially for Web developers that are not GIS-experts, slow down the development of applications using the infrastructure.
Therefore, in practice, Inspire competes with the Open Data as the objectives of both actions are close and should be more complementary than competitive.
  Making this statement, this reflection is to provide a data publishing technical framework that :
  * simplifies publishing datasets for public authorities, a priori, not familiar with the current technical framework of Inspire and
  * facilitates the use of the infrastructure by Web programmers that are not GIS-experts

## The basic ideas

This reflexion is based on the following ideas:
  * Data are managed in web applications and we shouldn't ask the user to write metadata, to publish them in a catalog
    and organize the links with discovery/view/download services.
    The web application (that I will call **thematic application**) that manages the data should also manage all the
    needed information and expose it on the web.
    Therefore the web application should be an Inspire download service
    and, because it knows the datasets, should also be able to expose the metadata of the datasets.
  * Some applications are able to draw maps and therefore can also easily be a view service.
    Others are not. If the way to style the information is defined, it seems possible to develop a generic mapping
    application that draws maps using this styling information to draw data downloaded from a thematic application.
  * A catalogue (also known as discovery service) is usefull to find information from the different thematic
    applications.
    Because the thematic applications already know the metadata, adding a new dataset or service to a catalogue shoud be
    simple, the only thing to do is to push them from the application to the catalogue.

## The CRS question

Understanding Coordinate Reference Systems (CRS) is a nightmare for non-GIS experts.
Therefore the goal here is to simplify this subject with 2 main ideas:
  * for **dowloading** in GeoJSON the two first coordinates will only be geographic coordinates expressed in ETRS89
    (or an ITS CRS over-seas)
    with potentially a third coordinate that can be, in application of Inspire regulation, one of the following:
      * ellipsoidal height above the GRS80 ellipsoid (http://www.opengis.net/def/crs/EPSG/0/4937),
      * on land, a height expressed in the European Vertical Reference System (EVRS)
       (http://www.opengis.net/def/crs/EPSG/0/7409),
      * in the free atmosphere, a height converted from barometric pressure using ISO 2533:1975 International Standard
        Atmosphere (http://inspire.ec.europa.eu/crs/ETRS89-GRS80-ISA),
      * in marine areas where there is an appreciable tidal range (tidal waters), a height above the Lowest Astronomical
        Tide (LAT) (http://inspire.ec.europa.eu/crs/ETRS89-GRS80-LAT),
      * in other marine areas, a height above the Mean Sea Level (MSL) or a well-defined reference level close to the
        MSL (http://inspire.ec.europa.eu/crs/ETRS89-GRS80-MSL).
        
      The choice for the potential third coordinates will be documented by a CRS URI.
      If only 2 coordinates are given, the CRS URI will be http://www.opengis.net/def/crs/EPSG/0/4258
      
  * for **viewing**, the OGC WMS 1.3 conventions will be followed and EPSG codes will be used.
    Default and recommanded CRS is the 'Spherical Mercator' CRS using well-known EPSG:3857 code.

## Metadata query specification
Query specifications

    {expr} ::=
        'and(' {expr} (',' {expr})* ')' // conjonction d'expressions
        'or(' {expr} (',' {expr})* ')' // disjonction d'expressions
        'not(' {expr} ')' // négation d'une expression
        'regExp(' {regExp} ',' {eltStr} (',' {string})? ')' // exp. régulière sur un élément de type chaine ou array(chaine) avec une option éventuelle
        'equals(' {eltEnum} ',' {string} ')' // test d'égalité entre un élément de type Enum et une valeur
        'contains(' {eltArrayEnum} ',' {string} ')' // test d'appartenance pour un élément de type array(Enum) et une valeur
        'hasKeyword(' {string} (',' ({string} | {regExp}))? ')' // existence d'un mot-clé défini par soit son libellé soit son URI plus éventuellement l'URI ou un RegExp du titre du CVOC
        'intersects(' {number} ',' {number} ',' {number} ',' {number} ')' // intersection avec un rectangle englobant défini par westBoundLongitude, southBoundLatitude, eastBoundLongitude, northBoundLatitude
        'before(' {date} ')' // date sur les données avant la date
        'after(' {date} ')' // date sur les données après la date
        'lessThan(' {resolution} ',' {number} ')' // resolution plus petite qu'une valeur
        'greaterThan(' {resolution} ',' {number} ')' // resolution plus grande qu'une valeur
        'conformsTo(' {spec} ')' // conformité à une spec
        'doesntConformTo(' {spec} ')' // non conformité à une spec
        'mdBefore(' {date} ')' // date de mise à jour des métadonnées avant la date
        'mdAfter(' {date} ')' // date de mise à jour des métadonnées après la date 
    {eltStr} ::=
        ('title' | 'abstract' | 'locator' | 'uri' | 'operatesOn' | 'lineage' | 'useLimitation' | 'responsibleParty.name' | 'responsibleParty.email' | 'mdContact.name' | 'mdContact.email') // les éléments de type chaine ou array(chaine) 
    {eltEnum} ::=
        ('type' | 'serviceType' | 'accessConstraints' | 'language') // les éléments de type Enum 
    {eltArrayEnum} ::=
        ('resourceLanguage' | 'topicCategory') // les éléments de type array(Enum) 
    {resolution} ::=
        ('max(spatialResolutionDistance)' | 'min(spatialResolutionDistance)' | 'max(spatialResolutionScaleDenominator)' | 'min(spatialResolutionScaleDenominator)') // resolution 
    {spec} ::=
        ({string} | {regExp}) // la spec est définie par un URI ou par un RegExp sur son titre 
    {string} ::=
        RegExp('[^']*') // définition d'une chaine par une expression régulière 
    {number} ::=
        RegExp([0-9]+(\.[0-9]+)?) // définition d'un nombre par une expression régulière 
    {regExp} ::=
        RegExp(/[^/]*/) // définition d'une expression régulière entre 2 / 
    {date} ::=
        RegExp([0-9][0-9][0-9][0-9](-[0-9][0-9](-[0-9][0-9])?)?) // définition d'une date par une expression régulière 

## The proposed solution

Technically, the solution is to add to a web application an API interface that implements at least a download service
and potentially a view service.
If the thematic application doesn't implement a view service, a mapping application will be used to make a view service
by using the data from the download service.
The catalogue can be generally a specific application distinct from thematic applications.

Defining the API specifications using the OAI formalism contributes to standardize the API
and simplifies the implementation for thematic applications.

A [preliminary version of OAI/Swagger definition for download and view services is available here](https://app.swaggerhub.com/apis/benoitdavidfr/inspireinoai).
The source file corresponds to
[inspireinoai.yaml](https://raw.githubusercontent.com/benoitdavidfr/inspireinoai/master/inspireinoai.yaml).

A [preliminary version of OAI/Swagger definition for a discovery service is available here](https://app.swaggerhub.com/apis/benoitdavidfr/discoveryinoai).
The source file corresponds to
[discoveryinoai.yaml](https://raw.githubusercontent.com/benoitdavidfr/inspireinoai/master/discoveryinoai.yaml).

A [preliminary version of OAI/Swagger definition for metadata is available here](https://app.swaggerhub.com/apis/benoitdavidfr/metadatainoai).
The source file corresponds to
[metadata.yaml](https://raw.githubusercontent.com/benoitdavidfr/inspireinoai/master/metadata.yaml).

These specifications are designed so that the thematic application implementing them will conform
the Inspire regulations.

These specifications can be used in several ways:
  * natively implement API to make a thematic application conformant to Inspire,
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
  