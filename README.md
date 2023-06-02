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
  * If you want converted data to be appended to an already existing graph, pass it as a second parameter, e.g.
    (continuing the code from the previous example):
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

### EasyRdf to rdfInterface

Conversion in this direction might get tricky. Important remarks:

* As the rdfInterface defines only an interface but no actual implementation,
  you must always pass an RDF terms factory object (the `$dataFactory` parameter).
* As the rdfInterface doesn't define a standardized way to create datasets 
  (`rdfInterface\DatasetInterface`) and dataset nodes (`rdfInterface\DatasetNodeInterface`)
  the `asRdfInterface()` method returns an `rdfInterface\QuadIteratorInterface`
  which is a triples iterator.
  **The only way to convert an `EasyRdf\Resource` or `EasyRdf\Graph` to
  a dataset or dataset node is to add triples from the EasyRdf object to an
  existing `rdfInterface\DatasetInterface` or `rdfInterface\DatasetNodeInterface`.**
  The `add()`, `addDataset()` and `addDatasetNode()` methods can be used for that
  (see examples below).
* There's an ambiguity around the `EasyRdf\Resource` conversion.
  You may want convert it to an RDF term (`rdfInterface\BlankNode` or `rdfInterface\NamedNode`)
  or you may want to convert it to a set of triples (quad iterator, dataset or dataset node).
  * The `asRdfInterface()` method converts to an RDF term if the `EasyRdf\Resource`
    object has no properties (no triples) and to a `rdfInterface\QuadIteratorInterface`
    otherwise.
  * Use `asTerm()`, `asQuadIterator()`, `add()`, `addDataset()` or `addDatasetNode()`
    to enforce a more specific behavior.

A sample EasyRdf graph and terms factory used in examples below:

```php
$graph = new EasyRdf\Graph();
$blank = $graph->resource('_:blank');
$res1  = $graph->resource('http://foo');
$res2  = $graph->resource('http://baz');
$res1->add('http://resource', $res2);
$lit1  = new EasyRdf\Literal('literal', 'en');
$lit2  = new EasyRdf\Literal(1, null, 'http://www.w3.org/2001/XMLSchema#integer');
$res1->addLiteral('http://langLiteral', $lit1);
$res1->addLiteral('http://intLiteral', $lit2);
$res3  = $graph->resource('http://marry');
$res3->addLiteral('http://langLiteral', $lit1);

$df = new quickRdf\DataFactory();
```

* Use `asRdfInterface()` to guess the output type based on the input.
  ```php
  print_r(rdfInterface2easyRdf\AsRdfInterface::asRdfInterface($blank, $df));
  # as $res2 contains no properties, it's converted to a named node
  print_r(rdfInterface2easyRdf\AsRdfInterface::asRdfInterface($res2, $df));
  print_r(rdfInterface2easyRdf\AsRdfInterface::asRdfInterface($lit1, $df));
  print_r(rdfInterface2easyRdf\AsRdfInterface::asRdfInterface($lit2, $df));
  # as $res1 contains properties, it's converted to a quad iterator
  print_r(rdfInterface2easyRdf\AsRdfInterface::asRdfInterface($res1, $df));
  foreach (rdfInterface2easyRdf\AsRdfInterface::asRdfInterface($res1, $df) as $i) {
    print_r($i);
  }
  # EasyRdf\Graph is also converted to a quad iterator
  print_r(rdfInterface2easyRdf\AsRdfInterface::asRdfInterface($graph, $df));
  foreach (rdfInterface2easyRdf\AsRdfInterface::asRdfInterface($graph, $df) as $i) {
    print_r($i);
  }
  ```
* Use `asTerm()` to enforce conversion of an `EasyRdf\Resource` to a term:
  ```php
  print_r(rdfInterface2easyRdf\AsRdfInterface::asRdfInterface($res1, $df));
  print_r(rdfInterface2easyRdf\AsRdfInterface::asTerm($res1, $df));
  ```
* There are two ways of converting an `EasyRdf\Graph` and `EasyRdf\Resource` to a dataset:
  ```php
  echo $graph->dump('text');

  # using quad iterator returned by the asRdfInterface()
  $dataset = new quickRdf\Dataset();
  $dataset->add(rdfInterface2easyRdf\AsRdfInterface::asRdfInterface($graph, $df));
  echo $dataset;

  # using add()/addDataset() method
  # (addDataset() works the same, just has strictly defined return type)
  $dataset = rdfInterface2easyRdf\AsRdfInterface::add($graph, $df, new quickRdf\Dataset());
  echo $dataset;
  
  # similarly for an EasyRdf\Resource
  # (just only given resource triples are converted)
  $dataset = new quickRdf\Dataset();
  $dataset->add(rdfInterface2easyRdf\AsRdfInterface::asRdfInterface($res1, $df));
  echo $dataset;
  $dataset = rdfInterface2easyRdf\AsRdfInterface::add($res1, $df, new quickRdf\Dataset());
  echo $dataset;
  # using add()/addDataset() we can also enforce 
  # a whole graph to be converted based on an EasyRdf\Resource
  $dataset = rdfInterface2easyRdf\AsRdfInterface::add($res1, $df, new quickRdf\Dataset(), true);
  echo $dataset;
  ```
* Conversion of an `EasyRdf\Resource` to a dataset node is relatively most complex:
  ```php
  echo $graph->dump('text');

  $emptyDatasetNode = new rdfHelpers\DatasetNode(new quickRdf\Dataset(), $df::blankNode());
  $datasetNode = rdfInterface2easyRdf\AsRdfInterface::add($res1, $df, $emptyDatasetNode);
  print_r($datasetNode->getNode());
  # the dataset attached to the dataset node contains all triples
  echo $datasetNode->getDataset();
  # but the dataset node itself returns only triples of the converted EasyRdf\Resource
  foreach($datasetNode as $i) {
    echo "$i\n";
  }

  # conversion could be limited to EasyRdf\Resource triples only using the $wholeGraph parameter
  $emptyDatasetNode = new rdfHelpers\DatasetNode(new quickRdf\Dataset(), $df::blankNode());
  $datasetNode = rdfInterface2easyRdf\AsRdfInterface::add($res1, $df, $emptyDatasetNode, false);
  print_r($datasetNode->getNode());
  # the dataset attached to the dataset node contains all triples
  echo $datasetNode->getDataset();

  # addDatasetNode() works in the same way, just has narrower return type
  $emptyDatasetNode = new rdfHelpers\DatasetNode(new quickRdf\Dataset(), $df::blankNode());
  $datasetNode = rdfInterface2easyRdf\AsRdfInterface::addDatasetNode($res1, $df, $emptyDatasetNode);
  print_r($datasetNode->getNode());
  echo $datasetNode->getDataset();
  ```
* In case of `add()`, `addDataset()` and `addDatasetNode()` the parameter used to
  pass a dataset/dataset node accepts also a callable, e.g.
  ```php
  $dataset = rdfInterface2easyRdf\AsRdfInterface::addDataset($res1, $df, fn() => new quickRdf\Dataset());

  $datasetNode = rdfInterface2easyRdf\AsRdfInterface::addDatasetNode(
    $res1, 
    $df, 
    fn($x) => new rdfHelpers\DatasetNode(new quickRdf\Dataset(), $x)
  );
  ```