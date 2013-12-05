<?php if (!defined('APPLICATION')) exit(); ?>
<h2 class="H"><?php echo T('Recent Profile Visitors') ?></h2>
<ul class="DataList ProfileVisitors">

<?php
$Visitors = $this->Data('Visitors');
   foreach ($Visitors as $Visitor) {
      $UserID = $Visitor['UserID'];
      $User = UserBuilder($Visitor);
?>
<li class="Item Activity" id="User_<?php echo $UserID; ?>">
   <div class="Author Photo">
      <?php echo UserPhoto($User); ?>   
   </div>

   <div class="ItemContent Activity">
      <div class="Title">
<?php
   if ($UserID == 0) {
      echo T('Guest', 'Some nosy guest');
   } else {
      echo UserAnchor($User, 'Username');
   }
   echo T(' on ').Gdn_Format::Date($Visitor['DateUpdated']);
?>
      </div>
      <!-- <div class="Excerpt">&nbsp;</div> -->
   </div>
</li>
<?php
      }
?>
</ul>
