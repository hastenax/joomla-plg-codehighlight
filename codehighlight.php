<?php

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('joomla.plugin.plugin');

class plgContentCodehighlight extends JPlugin {
	
	const _tag="code";
	
	private static $langAliases = array("as3" => "as3", "actionscript3" => "as3",
										"bash" => "bash", "shell" => "bash",
										"cpp" => "cpp", "c" => "cpp",
										"csharp" => "csharp", "c#" => "csharp", "c-sharp" => "csharp",
										"css" => "css",
										"pascal" => "pascal", "delphi" => "pascal",
										"diff" => "diff", "patch" => "diff",
										"groovy" => "groovy",
										"java" => "java",
										"javafx" => "javafx", "jfx" => "javafx",
										"js" => "javascript", "javascript" => "javascript", "jscript" => "javascript",
										"perl" => "perl", "pl" => "perl", "Perl" => "perl",
										"php" => "php",
										"text" => "text", "plain" => "text",
										"powershell" => "powershell", "ps" => "powershell",
										"python" => "python", "py" => "python",
										"ruby" => "ruby", "rails" => "ruby", "ror" => "ruby",
										"scala" => "scala",
										"sql" => "sql",
										"vb" => "vb", "vbnet" => "vb", "VisualBasic" => "vb",
										"xml" => "xml", "xhtml" => "xml", "xslt" => "xml", "html" => "xml",
										"applescript" => "applescript", "coldfusion" => "coldfusion", "sass" => "sass", "erlang" => "erlang"
										);
	
	private static $langFiles = array(  "as3"			=> "shBrushAS3.js",
										"bash"			=> "shBrushBash.js",
										"cpp"			=> "shBrushCpp.js",
										"csharp"		=> "shBrushCSharp.js",
										"css"			=> "shBrushCss.js",
										"pascal"		=> "shBrushDelphi.js",
										"diff"			=> "shBrushDiff.js",
										"groovy"		=> "shBrushGroovy.js",
										"java"			=> "shBrushJava.js",
										"javafx"		=> "shBrushJavaFX.js",
										"javascript"	=> "shBrushJScript.js",
										"perl"			=> "shBrushPerl.js",
										"php"			=> "shBrushPhp.js",
										"text"			=> "shBrushPlain.js",
										"powershell"	=> "shBrushPowerShell.js",
										"python"		=> "shBrushPython.js",
										"ruby"			=> "shBrushRuby.js",
										"scala"			=> "shBrushScala.js",
										"sql"			=> "shBrushSql.js",
										"vb"			=> "shBrushVb.js",
										"xml"			=> "shBrushXml.js",
										"applescript" => "shBrushAppleScript.js",
										"coldfusion" => "shBrushColdFusion.js",
										"sass" => "shBrushSass.js",
										"erlang" => "shBrushErlang.js"
	);
	
	private $_includeCSSOnce = False;
	private $_includedScripts = array();
	private $_runHighlighterOnce=False;

  //onPrepareContent event handler. Replace all entries of {codecitation} tag and load necessary scripts
  public function onContentPrepare($context, &$row, &$params, $limitstart) {

  	$tag=self::_tag;
  	$alternativetag=$this->params->def( 'alternativetag', '');
  	if ($alternativetag!='')
  	{
  		$tag='('.$tag.'|'.$alternativetag.')'; 		
  	}
  	else
  	{
  		$tag='('.$tag.')';
  	}
    $regex = "#{".$tag."[\s|&nbsp;]*(.*?)}([\s\S]*?){/\\1}#s";
    // register the regular expresstion to invoke our replacer in case Joomla finds the matches
    $row->text = preg_replace_callback( $regex, array($this,'replacer'), $row->text );
    return true;
  }

  //Do the replacement work to replace {codecitation}{/codecitation} into <div><pre></pre></div>
  //and include scripts as necessary
  private function replacer( &$matches ) {
    jimport('domit.xml_saxy_shared');

    //adjust document header to include SyntaxHighlighter styles and core
	$this->includeCSSOnce();
 	$text = $matches[3];
	
	//$args = array();
    //$args = SAXY_Parser_Base::parseAttributes( $matches[2] );
	$matches2 = array('', '');
    
	preg_match('/class="([^"]*)"/i', $matches[2], $matches2);
	
    //$shclass = JArrayHelper::getValue( $args, 'class', '' );
	$shclass =  (isset($matches2[1])) ? $matches2[1] : '' ;
	
	preg_match('/width="([\d]*px)"/i', $matches[2], $matches2);
	
    //$width = JArrayHelper::getValue( $args, 'width', 'inherit' );
	$width =  (isset($matches2[1])) ? $matches2[1] : '' ;
	
	//determine language to show
    $langAlias="";
    $regexLangAlias="#brush\s*:\s*(\S[^;]*[^;\s])#s";

    if (preg_match($regexLangAlias,$shclass,$langAliasMatches)>0) 
    {
    	$langAlias=$langAliasMatches[1];
    }
    else
    {
    	$langAlias=$this->params->def( 'defaultlang', 'text');
    	$shclass='brush:'.$langAlias.';'.$shclass;
    }
	
    //determine if we need to include xml markup parser for html scripts
    $htmlScriptMode=False;
    $regexHtmlScriptMode="#html-script\s*:\s*(\S[^;]*[^;\s])#s";
     if (preg_match($regexHtmlScriptMode,$shclass,$htmlScriptModeMatches)>0) 
    {
    	$htmlScriptMode=strtoupper($htmlScriptModeMatches[1]);
    }
    else
    {
    	$htmlScriptMode="FALSE";
    }    
    
    //include Java script that is necessary to show the code (if we have not already did it for this language)
    $codes=$this->includeScript($langAlias,$htmlScriptMode);
    
    $prolog='<div style="overflow: hidden; display: block; height: auto; width: '.$width.';"><pre class="'.$shclass.'">';
    $epilog='</pre></div>';

    $text = $codes.$prolog.$text.$epilog;

    return $text;
  }
  
  private function includeScript($langAlias, $htmlScriptMode) {
  	//get then lanuage name for the alias
  	$langName="text"; //IN CASE OF WRONG ALIAS WE WILL TREAT IT AS PLAIN TEXT

  	if (isset(self::$langAliases[$langAlias]))
  	{
  		$langName=self::$langAliases[$langAlias];
  	}
  	//get the filename for the language
  	$fileName="";
  	if (isset(self::$langFiles[$langName]))
  	{
  		$fileName=self::$langFiles[$langName];
  	}
  	else
  	{
  		$fileName=self::$langFiles["text"];
  	}
  	//check if we have already embeeded file into the page
  	if (!isset($this->_includedScripts[$fileName]))
  	{
  		$this->_includedScripts[$fileName]="1";
  		$this->updatePageIncludes($fileName);
  	}
  	if($htmlScriptMode=="TRUE")
  	{
  		//embeed xml lang file if necessary for html-script mode
  		if (!isset($this->_includedScripts[self::$langFiles[self::$langAliases["xml"]]]))
  		{
  			$this->_includedScripts[self::$langFiles[self::$langAliases["xml"]]]="1";
  			$this->updatePageIncludes(self::$langFiles[self::$langAliases["xml"]]);
  		}
  	}

  	//since we are here, we need to embeed styles and scripts into document
  	if ($this->_runHighlighterOnce) return "";
  	$this->_runHighlighterOnce=True;
  	$pluginRoot=self::getPluginRoot();
  	//TODO: Handle plugin parameters and set default SyntaxHighlighter values according to the plugin params vaules
  	$codes='<script type="text/javascript">
	SyntaxHighlighter.config.clipboardSwf = "'.$pluginRoot.'scripts/clipboard.swf";
	SyntaxHighlighter.defaults["auto-links"] = '.($this->params->def( 'auto-links', 0) ? 'true' : 'false').';
	SyntaxHighlighter.defaults["collapse"] = '.($this->params->def( 'collapse', 0) ? 'true' : 'false').';
	SyntaxHighlighter.defaults["gutter"] = '.($this->params->def( 'gutter', 0) ? 'true' : 'false').';
	SyntaxHighlighter.defaults["smart-tabs"] = '.($this->params->def( 'smart-tabs', 0) ? 'true' : 'false').';
	SyntaxHighlighter.defaults["tab-size"] = '.$this->params->def( 'tab-size', '4').';
	SyntaxHighlighter.defaults["toolbar"] = '.($this->params->def( 'toolbar', 0) ? 'true' : 'false').';
	SyntaxHighlighter.defaults["wrap-lines"] = '.($this->params->def( 'wrap-lines', 0) ? 'true' : 'false').';	
	SyntaxHighlighter.all();
</script>';
  	return $codes;
  }

  private function updatePageIncludes($fileName)
  {
  	$pluginRoot=self::getPluginRoot();
  	$tag='<script type="text/javascript" src="'.$pluginRoot.'scripts/'.$fileName.'"></script>';
 	$document= & JFactory::getDocument();
  	if ($document)
  	{
  		$document->addCustomTag($tag);
  	}
  	return;  	
  }
  
  //include necessary styles into page only once
  private function includeCSSOnce() {
  	//since we are here, we need to embeed styles and scripts into document
  	if ($this->_includeCSSOnce) return;
  	// we want only one link to the CSS
  	$this->_includeCSSOnce = True;
  	$pluginRoot=self::getPluginRoot();
  	$tag= '<link type="text/css" rel="stylesheet" href="'.$pluginRoot.'styles/shCore.css"/>
<link type="text/css" rel="stylesheet" href="'.$pluginRoot.'styles/'.$this->params->def( 'theme', 'shThemeDefault.css').'"/>
<script type="text/javascript" src="'.$pluginRoot.'scripts/shCore.js"></script>';
  	$document= & JFactory::getDocument();
  	if ($document)
  	{
  		$document->addCustomTag($tag);
  	}
  	return;
  }

  public static function getPluginRoot()
  {
  	return JURI::root().'plugins/content/codehighlight/';
  }
  
}