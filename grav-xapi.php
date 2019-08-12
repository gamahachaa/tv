<?php

namespace Grav\Plugin;

use Grav\Common\Plugin;
use RocketTheme\Toolbox\Event\Event;
use Grav\Common\Page\Page;
use Grav\Common\User\User;

/**
 * Class XapiPlugin
 * @package Grav\Plugin
 */
class GravXapiPlugin extends Plugin {

    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    protected $grav;
    protected $user;
    protected $time;
    protected $lrs;
    protected $page;
    protected $pname;
    protected $cache;

    public static function getSubscribedEvents() {
        return [
            'onPageInitialized' => ['onPageInitialized', 0],
            'onPluginsInitialized' => [
                    ['autoload', 100000],
                    ['onPluginsInitialized', 0]
            ]
        ];
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized() {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }
        $this->cache = $this->grav['cache'];
        $this->pname = 'grav-xapi';
        // Check to ensure login plugin is enabled.
        if (!$this->grav['config']->get('plugins.login.enabled')) {
            throw new \RuntimeException('The Login plugin needs to be installed and enabled');
        }
    }

    /**
     * [onPluginsInitialized:100000] Composer autoload.
     *
     * @return ClassLoader
     */
    public function autoload() {
        return require __DIR__ . '/vendor/autoload.php';
    }

    /**
     * Do some work for this event, full details of events can be found
     * on the learn site: http://learn.getgrav.org/plugins/event-hooks
     *
     * @param Event $e
     */
    public function onPageInitialized(Event $e) {
        // Get a variable from the plugin configuration
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            return;
        }
        $grav = $this->grav;
        $this->user = $grav['user'];
        if (!$this->user->authorize('site.login')) {
            return;
        }
        $this->page = $e['page'];
        // SET CONNEXION to the LRS
        $this->prepareLRS($this->user);
        // send statement if not filtered
        if ($this->filter()) {

            $statement = $this->prepareStatement($e['page']);
            // SEND STATEMENT
            $response = $this->lrs->saveStatement($statement);
            if ($response) {

                $this->grav['debugger']->getCaller();
                $this->grav['debugger']->addMessage('success');
            } else {

                $this->grav['debugger']->addMessage('failed');
                $this->grav['debugger']->addMessage($statement);
            }
        }
    }
    /**
     * 
     * @return boolean
     */
    private function filter() {
        // do not track routes and uri queries
        if ($this->grav['config']->get('plugins.' . $this->pname . '.filter.uri')) {
            $uri = $this->grav['uri'];
            /**
             * @todo add wild cards
             */
            // routes
            if ($this->grav['config']->get('plugins.' . $this->pname . '.filter.uri.routes')) {
                $filtered_routes = $this->grav['config']->get('plugins.' . $this->pname . '.filter.uri.routes');
                foreach ($filtered_routes as $v) {
                    if($uri->route() === $v ) return false;
                }
            }
            // queries
            if ($this->grav['config']->get('plugins.' . $this->pname . '.filter.uri.query')) {
                $filtered_queries = $this->grav['config']->get('plugins.' . $this->pname . '.filter.uri.query');
                foreach ($filtered_queries as $v) {
                    if($uri->query($v['key']) === $v['value'] ) return false;
                }
            }
        }
        // DO not track modular's modules (does not affect pages made of collections)
        if ($this->page->modular())
            return false;
        
        // Do not track a certain page based on its tempoale
        if ($this->grav['config']->get('plugins.' . $this->pname . '.filter.template') && in_array($this->page->template(), $this->grav['config']->get('plugins.' . $this->pname . '.filter.template')))
            return false;
        // Do not track users
        if ($this->grav['config']->get('plugins.' . $this->pname . '.filter.users') && in_array($this->user->login, $this->grav['config']->get('plugins.' . $this->pname . '.filter.users'))) {
            return false;
        }
        // Do not track users if they belong to acertain group
        if ($this->grav['config']->get('plugins.' . $this->pname . '.filter.groups')) {
            foreach ($this->user->groups as $g) {
                if (in_array($g, $this->grav['config']->get('plugins.' . $this->pname . '.filter.groups'))) {
                    return false;
                }
            }
        }
        // do not track pages with particular taxo
        $sysTaxo = $this->grav['config']->get('site.taxonomies');
        $pageTaxo = $this->page->taxonomy();
        foreach ($sysTaxo as $t) {
            $filterTaxo = $this->grav['config']->get('plugins.' . $this->pname . '.filter.taxonomies.' . $t);
            if (isset($filterTaxo) && isset($pageTaxo[$t])) {
                foreach ($filterTaxo as $ft) {
                    if (in_array($ft, $pageTaxo[$t])) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    /**
     * Get the LRS base on the config group mapping to list of LRS
     * Sets the connection to the first found matching LRS
     * @param User $u
     */
    protected function prepareLRS(User $u) {
        $config = 'default';
        // find the user's first group available in the plugin config and track to that LRS's client
        if (isset($u->groups)) {
            foreach ($u->groups as $g) {
                if ($this->grav['config']->get('plugins.' . $this->pname . '.lrs.' . $g)) {
                    $config = $g;
                    break;
                }
            }
        }
        /**
         * @todo add version in the config
         */
        $endpoint = $this->grav['config']->get('plugins.' . $this->pname . '.lrs.' . $config . '.endpoint');
        $username = $this->grav['config']->get('plugins.' . $this->pname . '.lrs.' . $config . '.username');
        $password = $this->grav['config']->get('plugins.' . $this->pname . '.lrs.' . $config . '.password');
        $this->lrs = new \TinCan\RemoteLRS(
                $endpoint, '1.0.1', $username, $password
        );
        /**
         * @todo cache this request
         */
//        try {
//            $about = $this->lrs->about();
//        } catch (ErrorException $e) {
//            //if can't connect debug or log
//            $this->grav['debugger']->addMessage($e);
//
//            $this->grav['debugger']->addMessage($this->lrs->getEndpoint());
//            $this->grav['debugger']->addMessage($endpoint);
//            $this->grav['debugger']->addMessage($username);
//            $this->grav['debugger']->addMessage($password);
//
//
//            $this->grav['log']->critical('GRAV XAPI cannot connect to LRS');
//            $this->grav['log']->error($e);
//            $this->grav['log']->info('OBJECT ENDPOINT ' . $this->lrs->getEndpoint());
//            $this->grav['log']->info('CONFIG ENDPOINT ' . $endpoint);
//            $this->grav['log']->info('CONFIG USER' . $username);
//            $this->grav['log']->info('CONFIG PWD' . $password);
//        }
    }

    /**
     * Get verb based on page template mapped to config list of templates/verbs
     * @param type $template
     * @return \TinCan\Verb
     */
    protected function prepareVerb($template) {
        if ($this->grav['config']->get('plugins.' . $this->pname . '.verb.' . $template)) {
            return new \TinCan\Verb([
                'id' => $this->grav['config']->get('plugins.' . $this->pname . '.verb.' . $template)
            ]);
        }
        return new \TinCan\Verb([
            'id' => $this->grav['config']->get('plugins.' . $this->pname . '.verb.default')
        ]);
    }

    /**
     * Send statement to LRS
     * @param \Grav\Plugin\Grav\Common\Page\Page $page
     * @return type
     */
    protected function prepareStatement(Page $page) {
        $header = $page->header();
        // WHO
        $actor = new \TinCan\Agent([
            'mbox' => 'mailto:' . $this->user->email,
            'name' => $this->user->login
        ]);

        // DID
        $verb = $this->prepareVerb($page->template());
        // WHAT
        $object = new \TinCan\Activity();
        $query = $this->grav['uri']->query() == '' ? '' : "?" . $this->grav['uri']->query();
        $activity_id = "https://" . $this->grav['uri']->host() . $this->grav['uri']->path() . $query;
        $object->setId($activity_id);
        $language = $page->language();
        /**
         * @todo make definition creation flexible before publishing.
         */
        $object->setDefinition([
            'name' => [
                $language => $page->title()
            ],
            'description' => [
                $language => isset($header->metadata) && isset($header->metadata['description']) ? $header->metadata['description'] : 'No description found',
            ],
            'type' => $page->template() == "listing" ? 'http://activitystrea.ms/schema/1.0/collection' : 'http://activitystrea.ms/schema/1.0/page'
        ]);
        // HOW
        $context = new \TinCan\Context();
        $context->setPlatform($this->grav['config']->get('site.title'));
        $context->setLanguage($language);
        // BUILD
        $statement = New \TinCan\Statement([
            'actor' => $actor,
            'verb' => $verb,
            'object' => $object,
            'context' => $context
        ]);
        return $statement;
    }

}
