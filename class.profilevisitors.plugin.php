<?php if (!defined('APPLICATION')) exit();
/**
 * Plugin to show a list of users who have visited your profile
 */

/**
 * TODOs
 * prio2: fix css for userphoto
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
   'RegisterPermissions' => array('Plugins.ProfileVisitors.View' => 1),
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
      // Set config settings only if they are not already set
      if (!C('Plugins.ProfileVisitors.MaxListCount')) {
         SaveToConfig('Plugins.ProfileVisitors.MaxListCount', '25');
      }
      if (!C('Plugins.ProfileVisitors.HideDeletedUsers')) {
         SaveToConfig('Plugins.ProfileVisitors.HideDeletedUsers', '0');
      }
      if (!C('Plugins.ProfileVisitors.NotifyVisit')) {
         SaveToConfig('Plugins.ProfileVisitors.NotifyVisit', '0');
      }
      if (!C('Plugins.ProfileVisitors.PurgeOnDisable')) {
         SaveToConfig('Plugins.ProfileVisitors.PurgeOnDisable', '0');
      }
      
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
      if (C('Plugins.ProfileVisitors.PurgeOnDisable') == TRUE ) {
         // remove all config entries
         RemoveFromConfig('Plugins.ProfileVisitors');
         // drop tables
         Gdn::Structure()->Table('Profilevisitor')->Drop();
         Gdn::Structure()->Table('User')->DropColumn('CountProfilevisitors');
      }
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
         'Plugins.ProfileVisitors.NotifyVisit' => array(
            'LabelCode' => 'Notify on profile visit',
            'Control' => 'Checkbox',
            'Default' => '0'
         ),
         'Plugins.ProfileVisitors.PurgeOnDisable' => array(
            'LabelCode' => 'Purge on disable',
            'Control' => 'Checkbox',
            'Default' => '0',
            'Description' => T('PurgeOnDisableDescription', 'Purge all settings and data when plugin is disabled.')
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
      if (!Gdn::Session()->CheckPermission('Plugins.ProfileVisitors.View')) {
         return;
      }

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
   public function ProfileController_Visitors_Create($Sender) {
      // calls function Controller_Index
      $this->Dispatch($Sender); 
   }

   public function Controller_Index($Sender) {
      $Sender->Permission(array(
         'Garden.Profiles.View',
         'Plugins.ProfileVisitors.View'
      ));
  
      // don't show edit profile menu
      $Sender->EditMode(FALSE); 
      // get user id
      $Session = Gdn::Session();
      $UserID = $Session->User->UserID;
      // set user info
      $Sender->GetUserInfo('', '', $UserID, TRUE);

      $Sender->SetData('Title', T('Profile Visitors'));
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
      
      // get list of visitors from db
      $Sender->SetData('Visitors', ProfileVisitorsModel::Get($UserID, $Limit));

      // render view
      $MyView = $this->GetView('visitors.php');
      $Sender->SetTabView(T('Profile Visitors'), $MyView);
      $ProfileView = $Sender->FetchViewLocation('index', 'ProfileController', 'dashboard');
      $Sender->Render($ProfileView);
   }

   /**
    * Adds visit to db and increase visit counter
    * 
    * @param Controller $Sender The object calling this method
    * @return void
    */
   public function ProfileController_BeforeRenderAsset_Handler($Sender) {
      // only do this for content of profile controller
      $AssetName = GetValueR('EventArguments.AssetName', $Sender);
      if ($AssetName != "Content") {
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
