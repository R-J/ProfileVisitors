<?php if (!defined('APPLICATION')) exit();

class ProfileVisitorsModel extends VanillaModel {

   /**
    * Returns list of vistors
    * 
    * @param integer $UserID User to look up in db
    * @param integer $Limit  limit for rows to fetch (optional, defaults to no limit)
    * @return array          Listing of profileuserid, visitor id, visitor name and date visited
    */
   public function Get($UserID, $Limit = '') {
      if (!is_numeric($UserID) || $UserID <= 0) {
         return;
      }      
      $Query = GDN::SQL();
      // check if resultset should be limited
      if (is_numeric($Limit) && $Limit > 0) {
         $Query->Limit($Limit);
      }
      // exclude deleted users if config is set
      if (C('Plugins.ProfileVisitors.HideDeletedUsers') == 1) {
         $Query->Where('u.Deleted <>', '1');
//            ->Join('User u', 'v.UpdateUserID = u.UserID', 'left outer');
      }

      // get visitor information from db, sort by last visit
      $Query->Select('v.ProfileUserID, v.UpdateUserID as UserID, v.DateUpdated, u.Name, u.Photo, u.Gender, u.Email')
         ->From('Profilevisitor v')
         ->Join('User u', 'v.UpdateUserID = u.UserID', 'left outer')
         ->Where('v.ProfileUserID', $UserID)
         ->OrderBy('v.DateUpdated', 'DESC');
      return $Query->Get()->ResultArray();
   }

   /**
    * Updates count of profile visitors in table user
    * 
    * @param integer $UserID user to update
    * @return integer visitor count
    */
   public function SetCount($UserID) {
      if (!is_numeric($UserID) || $UserID <= 0) {
         return;
      }     
      $Query = GDN::SQL();
      // exclude deleted users if config is set
      if (C('Plugins.ProfileVisitors.HideDeletedUsers') == 1) {
         $Query->Where('u.Deleted <>', '1')
            ->Join('User u', 'v.UpdateUserID = u.UserID', 'left outer');
      }
      // Get count
      $Query->Select('v.UpdateUserID', 'count')
         ->From('Profilevisitor v')
         ->Where('v.ProfileUserID', $UserID);
      $Result = $Query->Get()->ResultArray();
      $CountProfilevisitors = $Result[0]['UpdateUserID'];

      // Set count
      $Query->Update('User')
         ->Set('CountProfilevisitors', $CountProfilevisitors)
         ->Where('UserID', $UserID)
         ->Put();
      return $CountProfilevisitors;
   }

   /**
    * Stores visitor and date of visit to db
    * 
    * @param integer $ProfileUserID ID of user who's profile is opened
    * @param integer $UpdateUserID  ID of user looking at profile
    * @return void
    */
   public function SaveVisit($ProfileUserID, $UpdateUserID) {
      if (!is_numeric($ProfileUserID) || !is_numeric($UpdateUserID) || $ProfileUserID <= 0 || $UpdateUserID < 0) {
         return;
      }
      GDN::SQL()
         ->History(TRUE, FALSE)
         ->Replace('Profilevisitor',
            array('ProfileUserID' => $ProfileUserID, 'UpdateUserID' => $UpdateUserID),
            array('ProfileUserID' => $ProfileUserID, 'UpdateUserID' => $UpdateUserID)
         );
   }
}
