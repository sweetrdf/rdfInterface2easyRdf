# rdfInterface2easyRdf

[![Latest Stable Version](https://poser.pugx.org/sweetrdf/rdfInterface2easyRdf/v/stable)](https://packagist.org/packages/sweetrdf/rdfInterface2easyRdf)
![Build status](https://github.com/sweetrdf/rdfInterface2easyRdf/workflows/phpunit/badge.svg?branch=master)
[![Coverage Status](https://coveralls.io/repos/github/sweetrdf/rdfInterface2easyRdf/badge.svg?branch=master)](https://coveralls.io/github/sweetrdf/rdfInterface2easyRdf?branch=master)
[![License](https://poser.pugx.org/sweetrdf/rdfInterface2easyRdf/license)](https://packagist.org/packages/sweetrdf/rdfInterface2easyRdf)

A library providing methods for converting between EasyRdf ([original library](https://github.com/easyrdf/easyrdf), [still maintained fork](https://github.com/sweetrdf/easyrdf))
and [rdfInterface](https://github.com/sweetrdf/rdfInterface) objects (in both directions).

Helpful especially when you have too much EasyRdf code to port it but you would like to develop new code using the rdfInterface ecosystem.

## Installation

* Obtain the [Composer](https://getcomposer.org)
* Run `composer require sweetrdf/rdfInterface2easyRdf`
* Install EasyRdf implementation of your choice, e.g. `composer require sweetrdf/easyrdf`.
* Install rdfInterface implementation of your choice, e.g. `composer require sweetrdf/quick-rdf`.

## Usage

### EasyRdf to rdfInterface



```php
use rdfInterface2easyRdf\AsRdfInterface as Converter;

include 'vendor/autoload.php';

# let's make a simple EasyRdf graph
$graph = new EasyRdf\Graph();
$res1 = $graph->resource('http://foo');
$res2 = $graph->resource('http://bar');
$res1->addLiteral('http://property', 'value');
$res1->addResource('http://property', $res2);

# we will need an rdfInterface terms factory
$dataFactory = new quickRdf\DataFactory();

```

### rdfInterface to EasyRdf

Conversion in this direction is straightforward:

* If you don't care about strong result type checks, just use the `rdfInterface2easyRdf\AsEasyRdf::asEasyRdf()` method, e.g.:
  ```php
  # let's prepare all kind of rdfInterface objects
  $blank = quickRdf\DataFactory::blankNode();
  $named = quickRdf\DataFactory::namedNode('http://foo');
  $literal = quickRdf\DataFactory::literal('bar', 'en');
  $quad = quickRdf\DataFactory::quad($blank, $named, $literal);
  $dataset = new quickRdf\Dataset();
  $dataset->add($quad);
  $node = $dataset->withTerm($named);

  print_r(rdfInterface2easyRdf\AsEasyRdf::AsEasyRdf($blank));
  print_r(rdfInterface2easyRdf\AsEasyRdf::AsEasyRdf($named));
  print_r(rdfInterface2easyRdf\AsEasyRdf::AsEasyRdf($literal));
  echo rdfInterface2easyRdf\AsEasyRdf::AsEasyRdf($quad)->getGraph()->dump('text');
  echo rdfInterface2easyRdf\AsEasyRdf::AsEasyRdf($node)->getGraph()->dump('text');
  echo rdfInterface2easyRdf\AsEasyRdf::AsEasyRdf($dataset)->dump('text');
  ```
  * If you want converted data to be appended to an already existing graph, pass it as a second parameter, e.g.:
    ```php
    $graph = new EasyRdf\Graph();
    $graph->resource('http://baz')->addLiteral('https://foo', 'other value');
    rdfInterface2easyRdf\AsEasyRdf::AsEasyRdf($quad, $graph);
    echo $graph->dump('text');
    ```
* If you care about stictly defined return data types, use `rdfInterface2easyRdf\AsEasyRdf::asLiteral()`,
  `rdfInterface2easyRdf\AsEasyRdf::asResource()` and `rdfInterface2easyRdf\AsEasyRdf::asGraph()`.
  * Each of them accepts only compatible input types, e.g. `rdfInterface2easyRdf\AsEasyRdf::asLiteral()` accepts only `rdfInterface\LiteralInterface`
  * `rdfInterface\NodeInterface` is accepted both by `rdfInterface2easyRdf\AsEasyRdf::asResource()` and `rdfInterface2easyRdf\AsEasyRdf::asGraph()`
  * An `EasyRdf\Graph` can be passed as an optional second parameter just as with `rdfInterface2easyRdf\AsEasyRdf::AsEasyRdf()`
    (see the example above)
