<?php
/*PhpDoc:
name: bnf.inc.php
title: Classes et fonctions utilisées pour décrire une BNF
classes:
doc: |
  voir https://fr.wikipedia.org/wiki/Forme_de_Backus-Naur
  Une BNF est décrite par un objet de la classe BNF
  A chaque classe (sauf BNF) est associée une fonction constructeur
  voir l'exemple de la définition du langage de requêtes sur les métadonnées
journal: |
  27/2/2018:
    4:45
      Ajout d'une explication en cas de non-conformité
  26/2/2018:
    10:30
      Tous les exemples fonctionnent
*/

/*PhpDoc: classes
name: class Tree
title: classe définissant un arbre n-aire
doc: |
  L'abre définit un label et des enfants.
  Le label est initialisé à la construction.
  Les enfants peuvent être ajoutés avec la méthode addChild(Tree).
  L'abre peut être cloné.
*/
class Tree {
  private $label; // string
  private $children; // array des enfants
  function __construct(string $label) { $this->label = $label; $this->children = []; }
  function addChild(Tree $child) { $this->children[] = $child; }
  function __clone() {
    $children = $this->children;
    $this->children = [];
    foreach ($children as $child)
      $this->children[] = clone $child;
  }
};

/*PhpDoc: classes
name: class Explain
title: classe permettant de construire l'explication de non-conformité
doc: |
  Cette classe statique enregistre à chaque succès de l'analyse la chaine restante à analyser
  et conserve uniquement le texte le plus court.
  A la fin de l'analyse en cas d'échec, son explication consiste à afficher jusqu'où l'analyse a été effectuée
*/
class Explain {
  static $text=null;
  static function trace(string $text) {
    if (!self::$text or (strlen(self::$text) > strlen($text)))
      self::$text = $text;
  }
  static function result(string $text0) {
    $len0 = strlen($text0) - strlen(self::$text);
    echo 'Explain: <u>',substr($text0, 0, $len0),'</u><b>',self::$text,"</b><br>\n";
  }
};

/*PhpDoc: classes
name: class BNF
title: classe ensemble de règles BNF
doc: |
  Un objet correspond à un ensemble de règles BNF et un symbole de départ
  A chaque règle un commentaire est associé.
  L'ensemble de règles est défini par un array de la forme: [ $comment => $rule ]
  où:
    - $comment est le commentaire décrivant la règle
    - $rule est un objet Rule qui définit formellement la règle
*/
class BNF {
  private $start; // le symbole de départ: string
  private $rules; // les règles: [ symbol => [['comment'=>comment, 'def'=>Def]]]
  
  function __construct(string $start /* symbole de départ */, array $rules) { 
// $rules: les règles: [ comment:string => rule:Rule]
    $this->start = $start;
    $this->rules = [];
    foreach ($rules as $comment => $rule) {
      $symbol = $rule->symbol();
      if (!isset($this->rules[$symbol]))
        $this->rules[$symbol] = [];
      $this->rules[$symbol][] = [
        'comment'=> $comment,
        'def'=> $rule->def(),
      ];
      $rule->def()->setBnf($this);
    }
  }
  
  function __toString() {
    $str = "<ul>\n";
    foreach ($this->rules as $symbol => $rules) {
      $str .= '<li>{'.$symbol."} ::=\n<ul>\n";
      foreach ($rules as $rule) {
        $str .= "<li>$rule[def] // $rule[comment]\n";
      }
      $str .= "</ul>\n";
    }
    $str .= "</ul>\n";
    return $str;
  }
  
  // teste si un texte est conforme à la définition d'un symbole,
  // si conforme alors renvoie [l'arbre résultant, le texte restant] sinon []
  function checkSymbol(string $text, string $symbol, bool $verbose=true): array {
    foreach ($this->rules[$symbol] as $rule) {
      //echo "<pre>rule="; print_r($rule); echo "</pre>\n";
      if ($verbose)
        echo "test BNF de $rule[comment]<br>\n";
      $check = $rule['def']->check($text, $verbose);
      if (!$check) {
        if ($verbose)
          echo "test BNF $symbol ko de $rule[comment]<br>\n";
      }
      else {
        if ($verbose)
          echo "test BNF $symbol OK de $rule[comment] returns \"$check[1]\"<br>\n";
        Explain::trace($check[1]);
        return $check;
      }
    }
    if ($verbose)
      echo "test BNF ko pour $symbol<br>\n";
    return [];
  }
  
  // teste si un texte est conforme à la BNF,
  // si conforme alors renvoie [l'arbre résultant, le texte restant] sinon []
  function check(string $text, bool $verbose=false): array {
    //$verbose = true; // decommenter pour verbose
    return $this->checkSymbol($text, $this->start, $verbose);
  }
};

/*PhpDoc: classes
name: class Rule
title: classe Règle BNF définissant un symbole
doc: |
  Un objet correspond à une règle BNF définissant un symbole
  La définition est un objet Def
*/
class Rule {
  private $symbol; // symbole défini: string
  private $def; // définition: Def
  function symbol() { return $this->symbol; }
  function def() { return $this->def; }
  function __construct(string $symbol, Def $def) { $this->symbol = $symbol; $this->def = $def; }
  function __toString() { return '{'.$this->symbol.'} ::= '.$this->def; }
};
function Rule(string $symbol, Def $def) { return new Rule($symbol, $def); }

/*PhpDoc: classes
name: class Def
title: Super-classe des diverses définitions possibles
*/
class Def {};

/*PhpDoc: classes
name: class Seq extends Def
title: Séquence d'objets plus option
doc: |
  Séquence d'objets Term, Symb, Choice, RegExp
  L'option vaut '' | '?' | '+' | '*'
*/
class Seq extends Def {
  private $seq; // array(Seq|Choice|Term|Symb|RegExp)
  private $option; // string : '' | '?' | '+' | '*'
  
  function __construct(array $seq, string $option) { $this->seq = $seq; $this->option = $option; }
  
  function setBnf(BNF $bnf) { foreach ($this->seq as $elt) { $elt->setBnf($bnf); } }
    
  function __toString() {
    return (!$this->option ? implode(' ',$this->seq) : '('.implode(' ',$this->seq).')'.$this->option);
  }
  
  // teste si un texte conforme à l'élément, si conforme alors renvoie [l'arbre résultant, le texte restant] sinon []
  function checkOneElt(string $text, $elt, bool $verbose=true): array {
    if ($verbose)
      echo "Test Seq1 de $elt<br>\n";
    $check = $elt->check($text, $verbose);
    if (!$check) {
      if ($verbose)
        echo "test Seq1 ko de $elt<br>\n";
      return [];
    }
    else {
      if ($verbose)
        echo "test Seq1 OK de $elt<br>\n";
      return $check;
    }
  }
  
  // teste si un texte est conforme, si conforme alors renvoie [l'arbre résultant, le texte restant] sinon []
  function check(string $text0, bool $verbose=true): array {
    //echo "<pre>seq="; print_r($this->seq); echo "</pre>\n";
    $tree = new Tree('Seq');
    $text = $text0;
    foreach ($this->seq as $elt) {
      if ($verbose)
        echo "Test Seq de $elt<br>\n";
      $check = $this->checkOneElt($text, $elt, $verbose); 
      if (!$check)
        break;
      $tree->addChild($check[0]);
      $text = $check[1];
    }
    if (!$check) {
      switch ($this->option) {
        case '':
        case '+':
          return [];
        case '*':
        case '?':
          return [new Tree('Seq'), $text0];
      }
    }
    // la première itération est OK
    if (in_array($this->option, ['','?'])) {
      Explain::trace($text);
      return [$tree, $text];
    }
    while(true) {
      $tree0 = clone $tree;
      $text0 = $text;
      foreach ($this->seq as $elt) {
        $check = $this->checkOneElt($text, $elt, $verbose); 
        if (!$check) {
          Explain::trace($text0);
          return [$tree0, $text0];
        }
        $tree->addChild($check[0]);
        $text = $check[1];
      }
    }
  }
};
function Seq(array $seq, string $option='') {
  foreach ($seq as $obj) {
    if (!in_array(get_class($obj),['Seq','Choice','Term','Symb','RegExp']))
      throw new Exception("dans Seq: objet de classe ".get_class($obj)." non prévue");
    //echo "get_class=",get_class($obj),"<br>\n";
  }
  if (!in_array($option,['','?','+','*']))
    throw new Exception("dans Seq: option='$option' non prévue");
  return new Seq($seq, $option);
}

/*PhpDoc: classes
name: class Choice extends Def
title: Choix entre différentes possibilités
*/
class Choice extends Def {
  private $choices; // array(Seq|Term|Symb|RegExp)
  function __construct(array $choices) { $this->choices = $choices; }
  function setBnf(BNF $bnf) { foreach ($this->choices as $elt) { $elt->setBnf($bnf); } }
  function __toString() { return '('.implode(' | ',$this->choices).')'; }
  
  // teste si un texte est conforme, si conforme alors renvoie [l'arbre résultant, le texte restant] sinon []
  function check(string $text, bool $verbose=true): array {
    foreach ($this->choices as $choice) {
      $check = $choice->check($text, $verbose);
      if ($check) {
        if ($verbose)
          echo "Test Choice OK pour $choice returns \"$check[1]\"<br>\n";
        Explain::trace($check[1]);
        return $check;
      }
    }
    if ($verbose)
      echo "Test Choice ko pour $this<br>\n";
    return [];
  }
};
function Choice(array $choices) {
  foreach ($choices as $obj) {
    if (!in_array(get_class($obj),['Seq','Term','Symb','RegExp']))
      throw new Exception("dans Choice: objet de classe ".get_class($obj)." non prévue");
    //echo "get_class=",get_class($obj),"<br>\n";
  }
  return new Choice($choices);
}

/*PhpDoc: classes
name: class Term
title: Terminal correspondant à une chaine
*/
class Term {
  private $str; // string
  function __construct(string $str) { $this->str = $str; }
  function setBnf(BNF $bnf) { }
  function __toString() { return "'".$this->str."'"; }
  
  // teste si un texte est conforme, si conforme alors renvoie [l'arbre résultant, le texte restant] sinon []
  function check(string $text, bool $verbose=true): array {
    if (strncmp($text, $this->str, strlen($this->str))==0) {
      $ret = substr($text, strlen($this->str));
      if ($verbose)
        echo "Test Term OK \"$this->str\" returns \"$ret\"<br>\n";
      Explain::trace($ret);
      return [new Tree('Term:'.$this->str), $ret];
    }
    else {
      if ($verbose)
        echo "Test Term ko \"$this->str\"<br>\n";
      return [];
    }
  }
};
function Term(string $str) { return new Term($str); }

/*PhpDoc: classes
name: class Symb
title: Non-terminal référençant un symbole dans une définition
*/
class Symb {
  private $str; // string
  private $bnf; // référence vers la BNF dans laquelle est définie le symbole
  function __construct(string $str) { $this->str = $str; }
  function setBnf(BNF $bnf) { $this->bnf = $bnf; }
  function __toString() { return '{'.$this->str.'}'; }
  // teste si un texte est conforme, si conforme alors renvoie le texte restant sinon false
  function check(string $text, bool $verbose=true): array {
    return $this->bnf->checkSymbol($text, $this->str, $verbose);
  }
};
function Symb($str) { return new Symb($str); }

/*PhpDoc: classes
name: class RegExp extends Def
title: Terminal correspondant à une expression régulière
doc: |
  J'utilise le caractère ! pour délimiter le pattern
  Si ce caractère existe dans le pattern cela plantera
*/
class RegExp extends Def {
  private $pattern; // string, ne comprend ni limiteur ni ^ ni $
  function __construct(string $pattern) { $this->pattern = $pattern; }
  function setBNF(BNF $bnf) {}
  function __toString() { return 'RegExp('.$this->pattern.')'; }
  
  // teste si un texte est conforme, si conforme alors renvoie [l'arbre résultant, le texte restant] sinon []
  function check(string $text, bool $verbose=true): array {
    if ($verbose)
      echo "pattern=$this->pattern<br>\n",
           "text=$text<br>\n";
    $pattern = $this->pattern;
    if (preg_match("!^($pattern)!", $text, $matches)) {
      $ret = substr($text, strlen($matches[0]));
      Explain::trace($ret);
      return [new Tree('RegExp:'.$matches[1]), $ret];
    }
    else
      return [];
  }
};
function RegExp($str) { return new RegExp($str); }
