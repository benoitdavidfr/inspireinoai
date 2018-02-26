<?php
/*PhpDoc:
name: mdquery.php
title: analyse d'une requête sur les métadonnées
doc: |
    {expr} ::= or({expr}, {expr}, ...)
    {expr} ::= and({expr}, {expr}, ...) 
    {expr} ::= not({expr})
    {expr} ::= regExp({regExp}, {eltStr}, {options}) // regexp sur {eltStr} avec {options}
    {eltStr} ::= title | abstract | locator | uri | operatesOn | lineage | useLimitation // les elts de type text ou array(text)
    {eltStr} ::= responsibleParty.name | responsibleParty.email | mdContact.name | mdContact.email
    {expr} ::= equals({eltEnum}, {string})
    {eltEnum} ::= type | serviceType | accessConstraints | language
    {expr} ::= contains({eltArrayEnum}, {string})
    {eltArrayEnum} ::= resourceLanguage | topicCategory
    {expr} ::= hasKeyword({string}, {string}) // le premier paramètre est soit le libellé soit un uri,
                                           // le second optionnel est l'URI ou un regexp sur le titre du CVOC
    {expr} ::= intersects({nombre},{nombre},{nombre},{nombre}) // intersection avec la bbox
    {expr} ::= between({date},{date}) | before({date}) | after({date})
    {expr} ::= lessThan({resolution}, {number}) | greaterThan({resolution}, {number})
    {resolution} ::= 'max(spatialResolutionDistance)' | 'min(spatialResolutionDistance)'
    {resolution} ::= 'max(spatialResolutionScaleDenominator)' | 'min(spatialResolutionScaleDenominator)'
    {expr} ::= conformsTo({spec}) | doesntConformTo({spec})
    {spec} ::= {uri} | {regexpOfTitle}
    {expr} ::= mdBetween({date},{date}) | mdBefore({date}) | mdAfter({date})
journal: |
  26/2/2018:
    commence à marcher
*/
require_once __DIR__.'/bnf.inc.php';

// Spécifications du langage de requêtes des métadonnées
$specs = [
  "conjonction d'expressions"
    => Rule('expr', Seq([Term('and('), Symb('expr'), Seq([Term(','),Symb('expr')],'*'), Term(')')])),
  "disjonction d'expressions"
    => Rule('expr', Seq([Term('or('), Symb('expr'), Seq([Term(','),Symb('expr')],'*'), Term(')')])),
  "négation d'une expression"
    => Rule('expr', Seq([Term('not('), Symb('expr'), Term(')')])),
  "exp. régulière sur un élément de type chaine ou array(chaine) avec une option éventuelle"
    => Rule('expr', Seq([Term('regExp('), Symb('regExp'), Term(','), Symb('eltStr'), 
                    Seq([Term(','), Symb('string')],'?'), Term(')')])),
  "les éléments de type chaine ou array(chaine)"
    => Rule('eltStr', Choice([
            Term('title'),Term('abstract'),Term('locator'),Term('uri'),Term('operatesOn'),
            Term('lineage'),Term('useLimitation'),
            Term('responsibleParty.name'),Term('responsibleParty.email'),
            Term('mdContact.name'),Term('mdContact.email')
          ])),
  "test d'égalité entre un élément de type Enum et une valeur"
    => Rule('expr', Seq([Term('equals('), Symb('eltEnum'), Term(','), Symb('string'), Term(')')])),
  "les éléments de type Enum"
    => Rule('eltEnum', Choice([Term('type'),Term('serviceType'),Term('accessConstraints'),Term('language')])),
  "test d'appartenance pour un élément de type array(Enum) et une valeur"
    => Rule('expr', Seq([Term('contains('), Symb('eltArrayEnum'), Term(','), Symb('string'), Term(')')])),
  "les éléments de type array(Enum)"
    => Rule('eltArrayEnum', Choice([Term('resourceLanguage'),Term('topicCategory')])),
  "existence d'un mot-clé défini par soit son libellé soit son URI plus éventuellement l'URI ou un RegExp"
      ." du titre du CVOC"
    => Rule('expr', Seq([Term('hasKeyword('), Symb('string'), 
                        Seq([Term(','), Choice([Symb('string'),Symb('regExp')])],'?'),
                        Term(')')])),
  "intersection avec un rectangle englobant défini par westBoundLongitude, southBoundLatitude, "
      ."eastBoundLongitude, northBoundLatitude"
    => Rule('expr', Seq([Term('intersects('), Symb('number'), Term(','), Symb('number'), Term(','),
                                             Symb('number'), Term(','), Symb('number'), Term(')')])),
  "date sur les données avant la date"
    => Rule('expr', Seq([Term('before('), Symb('date'), Term(')')])),
  "date sur les données après la date"
    => Rule('expr', Seq([Term('after('), Symb('date'), Term(')')])),
  "resolution plus petite qu'une valeur"
    => Rule('expr', Seq([Term('lessThan('), Symb('resolution'), Term(','), Symb('number'), Term(')')])),
  "resolution plus grande qu'une valeur"
    => Rule('expr', Seq([Term('greaterThan('), Symb('resolution'), Term(','), Symb('number'), Term(')')])),
  "resolution"
    => Rule('resolution', Choice([
                            Term('max(spatialResolutionDistance)'),
                            Term('min(spatialResolutionDistance)'),
                            Term('max(spatialResolutionScaleDenominator)'),
                            Term('min(spatialResolutionScaleDenominator)')])),
  "conformité à une spec"
    => Rule('expr', Seq([Term('conformsTo('), Symb('spec'), Term(')')])),
  "non conformité à une spec"
    => Rule('expr', Seq([Term('doesntConformTo('), Symb('spec'), Term(')')])),
  "la spec est définie par un URI ou par un RegExp sur son titre"
    => Rule('spec', Choice([Symb('string'),Symb('regExp')])),
  "date de mise à jour des métadonnées avant la date"
    => Rule('expr', Seq([Term('mdBefore('), Symb('date'), Term(')')])),
  "date de mise à jour des métadonnées après la date"
    => Rule('expr', Seq([Term('mdAfter('), Symb('date'), Term(')')])),
  "définition d'une chaine par une expression régulière"
    => Rule('string', RegExp("'[^']*'")),
  "définition d'un nombre par une expression régulière"
    => Rule('number', RegExp('[0-9]+(\.[0-9]+)?')),
  "définition d'une expression régulière entre 2 /"
    => Rule('regExp', RegExp('/[^/]*/')),
  "définition d'une date par une expression régulière"
    => Rule('date', RegExp('[0-9][0-9][0-9][0-9](-[0-9][0-9](-[0-9][0-9])?)?')),
];
$specs = new BNF('expr', $specs);

echo "<html><head><meta charset='UTF-8'><title>mdquery</title></head><body>\n";
switch (isset($_GET['action']) ? $_GET['action'] : null) {
  case 'spec':
    echo  "<h2>Query specifications</h2>\n",
          $specs,
          "<a href='?action=examples'>query examples</a>\n";
    echo "<pre>"; print_r($specs);
    die();
    
  case 'examples':
    echo  "<h2>Query examples</h2>\n<ul>\n";
    foreach([
      "titre contient eau" => "regExp(/eau/,title,'i')",
      "type = 'dataset'" => "equals(type,'dataset')",
      "topicCategory contains 'boundaries'" => "contains(topicCategory,'boundaries')",
      "contient le mot-clé 'urbanisme'" => "hasKeyword('urbanisme')",
      "contient le mot-clé 'http://inspire.ec.europa.eu/theme/lu'"
         => "hasKeyword('http://inspire.ec.europa.eu/theme/lu')",
      "contient le mot-clé 'urbanisme' du CVOC URBAMET" => "hasKeyword('urbanisme',/URBAMET/)",
      "responsibleParty.name contient DREAL" => "regExp(/DREAL/, responsibleParty.name, 'i')",
      "intersects a bbox" => "intersects(0.0, 45.0, 1.0, 46.0)",
      "date entre 2 dates" => "between(2010,2015)",
      "date postérieure à une date" => "after(2010)",
      "resolution" => "lessThan(max(spatialResolutionScaleDenominator), 1000)",
      "conforme à la spec usage du sol" => "conformsTo('http://inspire.ec.europa.eu/theme/lu')",
      "conforme à la spec CNIG des PLU" => "conformsTo(/CNIG-PLU/)",
      "date des MD postérieure à une date" => "mdAfter(2018-01-15)",
      "titre contient eau et date des MD postérieure à une date"
         => "and(regExp(/eau/, title, 'i'),mdAfter(2018-01-15))",
    ] as $title => $query)
      echo "<li><a href='?query=",urlencode($query),"'>$title\n";
    echo "</ul>\n";
    die();
}

$query = (isset($_GET['query']) ? htmlspecialchars($_GET['query']) : '' );
echo <<<EOT
  <h2>Query</h2>
  <table border=1><form>
    <tr><td>
      <textarea rows=20 cols=90 name='query'>$query</textarea>
    </td></tr>
    <tr><td>
    <center><input type='submit' value='Envoi'></center>
    </td></tr>
  </form></table>
  <a href='?action=spec'>query specifications</a>,
  <a href='?action=examples'>query examples</a><br>
EOT;
if (!isset($_GET['query']))
  die("empty query<br>\n</body></html>\n");

/*
class Tree {
  private $label; // string
  private $children; // array des enfants
  
  // retourne la chaine restant à analyser
  function analyze(string $token, string $query): string {
    if ($token == 'expr') {
      if (!preg_match('!^([^(]+)\(!', $query, $matches))
        throw new Exception("No match line ".__LINE__);
      //echo "<pre>matches="; print_r($matches); echo "</pre>\n";
      $this->label = $matches[1];
      $query = substr($query, strlen($matches[0]));
      if (in_array($matches[1],['and','or','not']))
        $token = 'expr';
      else
        $token = 'param';
      $this->children[0] = new Tree;
      $query = $this->children[0]->analyze($token, $query);
      //echo "res=$query";
      while (substr($query,0,1)==',') {
        $query = substr($query,1);
        $child = new Tree;
        $this->children[] = $child;
        $query = $child->analyze($token, $query);
        //echo "res=$query";
      }
      if (substr($query,0,1)==')')
        return substr($query,1);
      else
        throw new Exception("No match line ".__LINE__);
    }
    elseif ($token == 'param') {
      
      if (preg_match('!^(/[^/]+/)!', $query, $matches) // regexp
          or preg_match('!^ *([a-z.]+)!i', $query, $matches) // nom d'élément
          or preg_match("!^ *('[^']*')!", $query, $matches) // chaine entre '
          or preg_match("!^ *([0-9][-0-9.]*)!", $query, $matches)) { // nombre ou date
        //echo "<pre>matches="; print_r($matches); echo "</pre>\n";
        $this->label = $matches[1];
        return substr($query,strlen($matches[0]));
      }
      else
        throw new Exception("No match line ".__LINE__);
    }
    else
      throw new Exception("No match line ".__LINE__);
  }
}

$tree = new Tree;
$tree->analyze('expr', $_GET['query']);
echo "<pre>tree="; print_r($tree); echo "</pre>\n";
*/
$result = $specs->check($_GET['query']);
if (!$result)
  echo "résultat ko<br\n";
else {
  if ($result[1] == '')
    echo "résultat OK vide<br>\n";
  else
    echo "resultat OK, reste: \"$result[1]\"<br>\n";
  echo "<pre>tree ="; print_r($result[0]);
}
