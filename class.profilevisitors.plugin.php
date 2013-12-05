<?php if (!defined('APPLICATION')) exit();
/**
 * Plugin to show a list of users who have visited your profile
 */

/**
 * TODOs
 * prio2: fix css for userphoto
 * prio2: fix view so that it looks like any other profilecontroller view
 * prio2: view permission depending on roles
 * prio3: look for better hook than base_render_before
 * prio9: do research concerning "$Sender->EditMode(FALSE)"
 * prio9: add activity for watching profile
 */

 $PluginInfo['ProfileVisitors'] = array(
   'Name' => 'Profile Visitors',
   'Description' => 'Tracks profile visits and gives user the option to see who has visited ',
   'Version' => '0.1',
   'Author' => 'Robin',
   'MobileFriendly' => TRUE,
   'RequiredApplications' => array('Vanilla' => '>=2.1'),
   'SettingsPermission' => 'Garden.Settings.Manage',
   'SettingsUrl' => '/settings/profilevisitors',
   'HasLocale' => TRUE
);

class ProfileVisitorsPlugin extends Gdn_Plugin {
   /**
    * Function is called when plugin is enabled in dashboard.
    * Saves default config settings and inits db changes
    * 
    * @return void
    */
   public function Setup() {
      SaveToConfig('Plugins.ProfileVisitors.MaxListCount', '25');
      SaveToConfig('Plugins.ProfileVisitors.HideDeletedUsers', '0');
      SaveToConfig('Plugins.ProfileVisitors.AllowedRoles', array('Administrator', 'Moderator', 'Member'));
      SaveToConfig('Plugins.ProfileVisitors.NotifyVisit', '0');
      
      $this->Structure();
   }

   /**
    * Creates extra table for profile visitors and
    * extra column in table User for count of visits
    *
    * @return void
    */
   public function Structure() {
      $Structure = Gdn::Structure();
      $Structure->Table('Profilevisitor')
         ->PrimaryKey('ID', 'int(11)')
         ->Column('ProfileUserID', 'int(11)', FALSE, 'index')
         ->Column('DateUpdated', 'datetime')
         ->Column('UpdateUserID', 'int(11)')
         ->Set(FALSE, FALSE);
      $Structure->Table('User')
         ->Column('CountProfilevisitors', 'int(11)', 0)
         ->Set(FALSE, FALSE);
   }
   
   /**
    * Function is called when plugin is disabled in dashboard
    * Removes config settings and resets db changes
    *
    * @return void
    */
   public function OnDisable() {
      // remove all config entries
      RemoveFromConfig('Plugins.ProfileVisitors');
      // drop tables
      Gdn::Structure()->Table('Profilevisitor')->Drop();
      Gdn::Structure()->Table('User')->DropColumn('CountProfilevisitors');
   }

   /**
    * Creates a settings screen for easy access to config options
    * 
    * @param SettingsController $Sender The object calling this method
    * @return void
    */ 
   public function SettingsController_ProfileVisitors_Create($Sender) {
      $Sender->Permission('Garden.Settings.Manage');
      $Sender->SetData('Title', T('Profile Visitors Settings'));
      $Sender->AddSideMenu('dashboard/settings/plugins');

      $RoleModel = new RoleModel();
      $AllRoles = $RoleModel->GetArray();      
      
      $Conf = new ConfigurationModule($Sender);
      $Conf->Initialize(array(
         'Plugins.ProfileVisitors.MaxListCount' => array(
            'LabelCode' => 'Number of visitors to show',
            'Control' => 'TextBox',
            'Default' => '25'
         ),
         'Plugins.ProfileVisitors.HideDeletedUsers' => array(
            'LabelCode' => 'Hide deleted users and guests from list',
            'Control' => 'Checkbox',
            'Default' => '0',
            'Description' => T('HideDeletedUsersDescription', 'If that setting is changed, the visitor count could be wrong the first time a user looks at his profile afterwards.')
         ),
         'Plugins.ProfileVisitors.AllowedRoles' => array(
            'LabelCode' => 'Select roles that are allowed to see their own visitorlist',
            'Control' => 'CheckboxList',
            'Items' => $AllRoles
         ),
         'Plugins.ProfileVisitors.NotifyVisit' => array(
            'LabelCode' => 'Add profile visits to activity',
            'Control' => 'Checkbox',
            'Default' => '0'
         )
      ));
      $Conf->RenderAll();
   }

   /**
    * Adds menu entry to profilevisitors in users own profile
    * 
    * @param ProfileController $Sender The object calling this method
    * @return void
    */
   public function ProfileController_AddProfileTabs_Handler($Sender){
      $ProfileUserID = $Sender->User->UserID;
      $UserID = Gdn::Session()->UserID;
      
      // only show menu entry in users own profile
      if ($UserID != $ProfileUserID) {
         return;
      }
      
      $ProfileVisitorsLabel = Sprite('SpProfileVisitors').' '.T('Profile Visitors');
      // show visitor count if config is set
      if (C('Vanilla.Profile.ShowCounts', TRUE)) {
         $ProfileVisitorsLabel .= '<span class="Aside">'.CountString(GetValueR('User.CountProfilevisitors', $Sender, NULL), "/profile/count/profilevisitors?userid=$UserID").'</span>';
      }
      $Sender->AddProfileTab(T('Profile Visitors'), 'profile/visitors/'.$ProfileUserID.'/'.rawurlencode($Sender->User->Name), 'ProfileVisitors', $ProfileVisitorsLabel);
   }

   /**
    * Renders list of profile visitors
    * 
    * @param ProfileController $Sender The object calling this method
    * @return void
    */
   public function ProfileController_Visitors_Create($Sender){
      // check for permission to view profile
      $Sender->Permission('Garden.Profiles.View');
// $Sender->EditMode(FALSE); // do i need that? don't think so...
      $Sender->SetData('Breadcrumbs', array(
            array('Name' => T('Profile'), 'Url' => '/profile'),
            array('Name' => T('Visitors'), 'Url' => '/profile/visitors'))
      );
      // check if output is limited
      $MaxListCount = C('Plugins.ProfileVisitors.MaxListCount');
      if ($MaxListCount >= 0 ) {
         $Limit = $MaxListCount;
      } else {
         $Limit = '';
      }
      $Sender->SetData('Limit', $Limit);
      // get list of visitors from db
      $Sender->SetData('Visitors', ProfileVisitorsModel::Get(Gdn::Session()->UserID, $Limit));
      // render view
      $Sender->Render('visitors', '', 'plugins/ProfileVisitors');
   }

   /**
    * Adds visit to db and increase visit counter
    * 
    * @param Controller $Sender The object calling this method
    * @return void
    */
   public function Base_Render_Before($Sender) {
      // only do this for Profile Controller
      if (get_class($Sender) != 'ProfileController') {
         return;
      }
      // if user is looking at own profile, just update count, then exit
      $UserID = Gdn::Session()->UserID;
      $ProfileUserID = $Sender->User->UserID;
      if ($UserID == $ProfileUserID || $ProfileUserID == '') {
         ProfileVisitorsModel::SetCount($ProfileUserID);
         return;
      }
      // save visit to db
      ProfileVisitorsModel::SaveVisit($ProfileUserID, $UserID);
      // update visitor counter in table user
      ProfileVisitorsModel::SetCount($ProfileUserID);
   }
}
