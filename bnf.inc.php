<?php
/*PhpDoc:
name: bnf.inc.php
title: Classes et fonctions utilisées pour décrire une BNF
classes:
doc: |
  voir https://fr.wikipedia.org/wiki/Forme_de_Backus-Naur
  Une BNF est décrite par un objet de la classe BNF
  A chaque classe (sauf BNf) est associée une fonction constructeur
  voir l'exemple de la définition du langage de requêtes sur les métadonnées
*/

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
  
  // teste si un texte est conforme à la définition d'un symbole, si conforme alors renvoie le texte restant sinon false
  function checkSymbol(string $text, string $symbol, bool $verbose=true) {
    foreach ($this->rules[$symbol] as $rule) {
      //echo "<pre>rule="; print_r($rule); echo "</pre>\n";
      if ($verbose)
        echo "test BNF de $rule[comment]<br>\n";
      $check = $rule['def']->check($text, $verbose);
      if ($check === false) {
        if ($verbose)
          echo "test BNF $symbol ko de $rule[comment]<br>\n";
      }
      else {
        if ($verbose)
          echo "test BNF $symbol OK de $rule[comment] returns \"$check\"<br>\n";
        return $check;
      }
    }
    if ($verbose)
      echo "test BNF ko pour $symbol<br>\n";
    return false;
  }
  
  // teste si un texte est conforme à la BNF, si conforme alors renvoie le texte restant sinon false
  function check(string $text, bool $verbose=true) {
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
  
  // teste si un texte est conforme à un élément, si conforme alors renvoie le texte restant sinon false
  function checkOneElt(string $text, $elt, bool $verbose=true) {
    if ($verbose)
      echo "Test Seq1 de $elt<br>\n";
    $check = $elt->check($text, $verbose);
    if ($check === false) {
      if ($verbose)
        echo "test Seq1 ko de $elt<br>\n";
      return false;
    }
    else {
      if ($verbose)
        echo "test Seq1 OK de $elt<br>\n";
      return $check;
    }
  }
  
  // teste si un texte est conforme, si conforme alors renvoie le texte restant sinon false
  function check(string $text0, bool $verbose=true) {
    //echo "<pre>seq="; print_r($this->seq); echo "</pre>\n";
    $text = $text0;
    foreach ($this->seq as $elt) {
      if ($verbose)
        echo "Test Seq de $elt<br>\n";
      $check = $this->checkOneElt($text, $elt, $verbose); 
      if ($check === false)
        break;
      else
        $text = $check;
    }
    if ($check === false) {
      switch ($this->option) {
        case '':
        case '+':
          return false;
        case '*':
        case '?':
          return $text0;
      }
    }
    // la première itération est OK
    if (in_array($this->option, ['','?']))
      return $text;
    while(true) {
      foreach ($this->seq as $elt) {
        $check = $this->checkOneElt($text, $elt, $verbose); 
        if ($check === false)
          break;
        else
          $text = $check;
      }
      if ($check === false)
        return $text;
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
  function check(string $text, bool $verbose=true) {
    foreach ($this->choices as $choice) {
      $check = $choice->check($text, $verbose);
      if ($check !== false) {
        if ($verbose)
          echo "Test Choice OK pour $choice returns \"$check\"<br>\n";
        return $check;
      }
    }
    if ($verbose)
      echo "Test Choice ko pour $this<br>\n";
    return false;
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
  
  // teste si un texte est conforme, si conforme alors renvoie le texte restant sinon false
  function check(string $text, bool $verbose=true) {
    if (strncmp($text, $this->str, strlen($this->str))==0) {
      $ret = substr($text, strlen($this->str));
      if ($verbose)
        echo "Test Term OK '$this->str' returns \"$ret\"<br>\n";
      return $ret;
    }
    else {
      if ($verbose)
        echo "Test Term ko '$this->str'<br>\n";
      return false;
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
  function check(string $text, bool $verbose=true) {
    return $this->bnf->checkSymbol($text, $this->str, $verbose);
  }
};
function Symb($str) { return new Symb($str); }

/*PhpDoc: classes
name: class RegExp extends Def
title: Terminal correspondant à une expression régulière
*/
class RegExp extends Def {
  private $pattern; // string, ne comprend ni limiteur ni ^ ni $
  function __construct(string $pattern) { $this->pattern = $pattern; }
  function setBNF(BNF $bnf) {}
  function __toString() { return 'RegExp('.$this->pattern.')'; }
  
  // teste si un texte est conforme, si conforme alors renvoie le texte restant sinon false
  function check(string $text, bool $verbose=true) {
    if ($verbose)
      echo "pattern=$this->pattern<br>\n",
           "text=$text<br>\n";
    if (!preg_match('!^([^/]*)!', $text, $matches))
      throw new Exception("No match");
    $subtext = $matches[1];
    $pattern = $this->pattern;
    if (preg_match("!^$pattern!", $subtext, $matches))
      return substr($text, strlen($matches[0]));
    else
      return false;
  }
};
function RegExp($str) { return new RegExp($str); }
