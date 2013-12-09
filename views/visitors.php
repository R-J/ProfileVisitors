<?php if (!defined('APPLICATION')) exit(); ?>
<h2 class="H"><?php echo T('Recent Profile Visitors') ?></h2>
<ul class="DataList ProfileVisitors Activities">

<?php
$Visitors = $this->Data('Visitors');
   foreach ($Visitors as $Visitor) {
      $UserID = $Visitor['UserID'];
      $User = UserBuilder($Visitor);
      $PhotoAnchor = UserPhoto($User);
?>
<li class="Item Activity" id="User_<?php echo $UserID; ?>">
  <?php
    if ($PhotoAnchor != '') {
   ?>
   <div class="Author Photo"><?php echo $PhotoAnchor; ?></div>
   <?php } ?>

   <div class="ItemContent Activity">
      <div class="Title">
<?php
   if ($UserID == 0) {
      echo T('Guest', 'Some nosy guest');
   } else {
      echo UserAnchor($User, 'Username');
   }
   ?>
      </div>
     <div class="Meta">
       <span class="MItem DateCreated"><?php echo Gdn_Format::Date($Visitor['DateUpdated']); ?></span>
     </div>
      <!-- <div class="Excerpt">&nbsp;</div> -->
   </div>
</li>
<?php
      }
?>
</ul>
