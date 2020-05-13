<?php

if (!defined('AOWOW_REVISION'))
    die('illegal access');


// menuId 5: Profiler g_initPath()
//  tabId 1: Tools    g_initHeader()
class ArenaTeamsPage extends GenericPage
{
    use TrProfiler;

    protected $type     = TYPE_ARENA_TEAM;

    protected $tabId    = 1;
    protected $path     = [1, 5, 3];
    protected $tpl      = 'arena-teams';
    protected $js       = ['filters.js', 'profile_all.js', 'profile.js'];

    public function __construct($pageCall, $pageParam)
    {
        if (!CFG_PROFILER_ENABLE)
            $this->error();

        $this->getSubjectFromUrl($pageParam);

        $this->filterObj = new ArenaTeamListFilter();

        foreach (Profiler::getRealms() as $idx => $r)
        {
            if ($this->region && $r['region'] != $this->region)
                continue;

            if ($this->realm && $r['name'] != $this->realm)
                continue;

            $this->sumSubjects += DB::Characters($idx)->selectCell('SELECT count(*) FROM arena_team');
        }

        parent::__construct($pageCall, $pageParam);

        $this->name   = Lang::profiler('arenaTeams');
        $this->subCat = $pageParam ? '='.$pageParam : '';
    }

    protected function generateTitle()
    {
        if ($this->realm)
            array_unshift($this->title, $this->realm,/* CFG_BATTLEGROUP,*/ Lang::profiler('regions', $this->region), Lang::profiler('arenaTeams'));
        else if ($this->region)
            array_unshift($this->title, Lang::profiler('regions', $this->region), Lang::profiler('arenaTeams'));
        else
            array_unshift($this->title, Lang::profiler('arenaTeams'));
    }

    protected function generateContent()
    {
        $this->addJS('?data=realms&locale='.User::$localeId.'&t='.$_SESSION['dataKey']);

        $conditions = [];
        if (!User::isInGroup(U_GROUP_EMPLOYEE))
            $conditions[] = ['at.rating', 1000, '>'];

        if ($_ = $this->filterObj->getConditions())
            $conditions[] = $_;

        // recreate form selection
        $this->filter = $this->filterObj->getForm();
        $this->filter['query']    = isset($_GET['filter']) ? $_GET['filter'] : null;
        $this->filter['initData'] = ['type' => 'arenateams'];

        $tabData = array(
            'id'          => 'arena-teams',
            'hideCount'   => 1,
            'sort'        => [-16],
            'extraCols'   => ['$Listview.extraCols.members'],
            'visibleCols' => ['rank', 'wins', 'losses', 'rating'],
            'hiddenCols'  => ['arenateam', 'guild'],
        );

        if (empty($this->filter['sz']))
            $tabData['visibleCols'][] = 'size';

        $miscParams = [];
        if ($this->realm)
            $miscParams['sv'] = $this->realm;
        if ($this->region)
            $miscParams['rg'] = $this->region;

        $teams = new RemoteArenaTeamList($conditions, $miscParams);
        if (!$teams->error)
        {
            $teams->initializeLocalEntries();

            $dFields = $teams->hasDiffFields(['faction', 'type']);
            if (!($dFields & 0x1))
                $tabData['hiddenCols'][] = 'faction';

            $tabData['data'] = array_values($teams->getListviewData());

            // create note if search limit was exceeded
            if ($this->filter['query'] && $teams->getMatches() > CFG_SQL_LIMIT_DEFAULT)
            {
                $tabData['note'] = sprintf(Util::$tryFilteringString, 'LANG.lvnote_arenateamsfound2', $this->sumSubjects, $teams->getMatches());
                $tabData['_truncated'] = 1;
            }
            else if ($teams->getMatches() > CFG_SQL_LIMIT_DEFAULT)
                $tabData['note'] = sprintf(Util::$tryFilteringString, 'LANG.lvnote_arenateamsfound', $this->sumSubjects, 0);

            if ($this->filterObj->error)
                $tabData['_errors'] = 1;
        }

        $this->lvTabs[] = ['profile', $tabData, 'membersCol'];

        Lang::sort('game', 'cl');
        Lang::sort('game', 'ra');
    }
}

?>
