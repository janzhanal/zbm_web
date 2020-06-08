<?php
namespace Grav\Plugin;
use Symfony\Component\Yaml\Yaml as Yaml;
use Grav\Common\Cache as Cache;
use RocketTheme\Toolbox\Event\Event;
use Grav\Common\Grav;
use Grav\Common\Helpers\LogViewer;
use \Grav\Common\Plugin;
class PHPPlugin extends Plugin
{
    public static function getSubscribedEvents()
    {
        return [
            'onTwigExtensions' => ['onTwigExtensions', 0],
            'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
			'onSchedulerInitialized' => ['onSchedulerInitialized', 0],
        ];
    }
    public function onTwigExtensions()
    {
        require_once(__DIR__ . '/twig/PhpTwigExtension.php');
        $this->grav['twig']->twig->addExtension(new PhpTwigExtension());
    }

    public function onTwigTemplatePaths()
	{
		// add templates to twig path
		$this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
	}




    public function onTwigSiteVariables()
	{
        // setup
        $twig = $this->grav['twig'];
		$page = $this->grav['page'];

		// only load the vars if calendar page
		if ($page->template() == 'logs')
        {			// add calendar to twig as calendar
            
            $twig->twig_vars['logviewer'] = new LogViewer();
		}


    }
    
    public function onSchedulerInitialized(Event $e)
    {
        require_once(__DIR__ . '/twig/PhpTwigExtension.php');
        $scheduler = $e['scheduler'];
        $job = $scheduler->addFunction('\Grav\Plugin\PHPPlugin::shiftPlan', [], 'shift-plan-for-this-week');
        $job->at('0 0 * * 1'); // every monday at 00:00

        $job = $scheduler->addFunction('\Grav\Plugin\PHPPlugin::shiftPlan2', [], 'shift-plan2-for-this-week');
        $job->at('0 0 * * 1'); // every monday at 00:00
        
        $job = $scheduler->addFunction('Grav\Plugin\PhpTwigExtension::importRacesFromMembers', [], 'import-races-from-members');
        $job->at('0 0 * * *'); 
    }

    // nastavi "pristi tyden" jako "tento tyden" a do "pristi tyden" nacte predchozi pouzitou sablonu - potreba CRON/ task scheduler 
    public static function shiftPlan(){
        require_once(__DIR__ . '/twig/PhpTwigExtension.php');
        $php = new PhpTwigExtension();

        $plan_path = './user/pages/auth/plan/default--plan-header.cs.md';
        $plan_next_path = './user/pages/auth/plan-next/default--plan-header.cs.md';
        /******************/
        // update this week
        /******************/
        $frontmatter = $php->parse_file_frontmatter_only($plan_next_path);
        $content = $php->parse_file_content_only($plan_path);
        $page = $php->combine_frontmatter_with_content($frontmatter, $content);

        $php->file_force_contents($plan_path, $page);

        /******************************/
        // load template for next week 
        /******************************/
        $template = $php->get_plan_template($plan_next_path);
        // alternate frontmatter
        $frontmatter = $php->get_frontmatter_as_array($plan_next_path);             
        $frontmatter['planTemplate'] = $template;                               // set last used template to the chosen one
        $frontmatter['plan'] = $php->get_plan_from_template($template);        // get chosen plan from page plan-templates
        
        $php->save_page_with_edited_frontmatter($plan_next_path, $frontmatter);

        $php->log_grav("Plan shifted");

        Cache::clearCache('cache-only');        
    }

    public static function shiftPlan2(){
        require_once(__DIR__ . '/twig/PhpTwigExtension.php');
        $php = new PhpTwigExtension();

        $plan_path = "./user/pages/auth/plan2/blank.md";
        $plan_frontmatter = $php->get_frontmatter_as_array($plan_path);
        $template_frontmatter = $php->get_frontmatter_as_array("./user/pages/auth/plan2/templates/blank.md");

        $default_template = $template_frontmatter["defaultTemplate"];
        $plan_frontmatter["plan"]["thisWeek"] = $plan_frontmatter["plan"]["nextWeek"];
        $plan_frontmatter["plan"]["nextWeek"] = $template_frontmatter["templates"][$default_template]["plan"];

        $php->save_page_with_edited_frontmatter($plan_path, $plan_frontmatter);

        $php->log_grav("Plan2 shifted");

        Cache::clearCache('cache-only');        
    }



    

}
?>
