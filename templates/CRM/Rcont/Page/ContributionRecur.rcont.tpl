{*-------------------------------------------------------+
| YfC UI modicications                                   |
| Copyright (C) 2015 SYSTOPIA                            |
| Author: B. Endres (endres@systopia.de)                 |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+-------------------------------------------------------*}

<a id="rcur-edit" href="{crmURL p='civicrm/rcontribution/edit' q="reset=1&rcid=`$recur.id`"}" class="button edit">Edit</a>

<script type="text/javascript">
cj("div.crm-submit-buttons").append(cj("#rcur-edit"));
</script>