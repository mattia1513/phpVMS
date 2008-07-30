<?php
/**
 * phpVMS - Virtual Airline Administration Software
 * Copyright (c) 2008 Nabeel Shahzad
 * For more information, visit www.phpvms.net
 *	Forums: http://www.phpvms.net/forum
 *	Documentation: http://www.phpvms.net/docs
 *
 * phpVMS is licenced under the following license:
 *   Creative Commons Attribution Non-commercial Share Alike (by-nc-sa)
 *   View license.txt in the root, or visit http://creativecommons.org/licenses/by-nc-sa/3.0/
 *
 * @author Nabeel Shahzad
 * @copyright Copyright (c) 2008, Nabeel Shahzad
 * @link http://www.phpvms.net
 * @license http://creativecommons.org/licenses/by-nc-sa/3.0/
 * @package module_admin_pilots
 */
 
class PilotAdmin extends CodonModule
{

	function HTMLHead()
	{
		switch($this->get->admin)
		{
			case 'viewpilots':
				Template::Set('sidebar', 'sidebar_pilots.tpl');
				break;
			case 'pendingpilots':
				Template::Set('sidebar', 'sidebar_pending.tpl');
				break;
			case 'pilotgroups':
				Template::Set('sidebar', 'sidebar_groups.tpl');
				break;
		}
	}
		
	function Controller()
	{

		switch($this->get->admin)
		{
			case 'viewpilots':

				/* This function is called for *ANYTHING* in that popup box
					
					Preset all of the template items in this function and
					call them in the subsequent templates
					
					Confusing at first, but easier than loading each tab
					independently via AJAX. Though may be an option later
					on, but can certainly be done by a plugin (Add another
					tab through AJAX). The hook is available for whoever
					wants to use it
				*/
				switch($this->post->action)
				{
					case 'changepassword':
				
						$this->ChangePassword();
						return;
				
						break;
				/* These are reloaded into the #pilotgroups ID
					so the entire groups list is refreshed
					*/
					case 'addgroup':
				
						$this->AddPilotToGroup();
						$this->SetGroupsData($this->post->pilotid);
						Template::Show('pilots_groups.tpl');
						return;
						
						break;
				
					case 'removegroup':
				
						$this->RemovePilotGroup();
						
						$this->SetGroupsData($this->post->pilotid);
						Template::Show('pilots_groups.tpl');
						return;
						break;
						
					case 'saveprofile':
						
						$pilotid = $this->post->pilotid;
						$email = $this->post->email;
						$location = $this->post->location;
						$hub = $this->post->hub;
						
						$flighttime = $this->post->totalhours;
						$numflights = $this->post->totalflights;
						
						// save all their profile stuff
						PilotData::SaveProfile($pilotid, $email , $location, $hub);
						PilotData::ReplaceFlightData($pilotid, $flighttime, $numflights);
						PilotData::SaveFields($pilotid, $_POST);
						
						Template::Set('message', 'Profile updated successfully');
						Template::Show('core_success.tpl');
						
						return;
						break;
				}
				
				
				if($this->get->action == 'viewoptions')
				{
					$this->ViewPilotDetails();
					return;
				}
				
				$this->ShowPilotsList();
				break;

			case 'pendingpilots':

                switch($this->post->action)
                {
					case 'approvepilot':
						PilotData::AcceptPilot(Vars::POST('id'));
						RanksData::CalculatePilotRanks();
						
						break;
					case 'rejectpilot':
						PilotData::RejectPilot(Vars::POST('id'));
						break;
				}

				Template::Set('allpilots', PilotData::GetPendingPilots());
				Template::Show('pilots_pending.tpl');
				break;
			
			case 'pilotgroups':
			
				if(Vars::POST('action') == 'addgroup')
				{
					$this->AddGroup();
				}
				
				$this->ShowGroups();
				break;
		}
		
	}
	
	function ShowPilotsList()
	{
		$letters = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I',
						 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R',
						 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z');
		
		Template::Set('allletters', $letters);
		Template::Set('allpilots', PilotData::GetAllPilots(Vars::GET('letter')));
		
		Template::Show('pilots_list.tpl');
	}
	
	function ViewPilotDetails()
	{
		$pilotid = $this->get->pilotid;
		
		//This is for the main tab
		Template::Set('pilotinfo', PilotData::GetPilotData($pilotid));
		Template::Set('customfields', PilotData::GetFieldData($pilotid, true));
		Template::Set('pireps', PIREPData::GetAllReportsForPilot($pilotid));
		
		$this->SetGroupsData($pilotid);
		
		Template::Show('pilots_detailtabs.tpl');
	}
	
	function SetGroupsData($pilotid)
	{
		//This is for the groups tab
		// Only send the groups they're in
		$freegroups = array();
		
		$allgroups = PilotGroups::GetAllGroups();
		foreach($allgroups as $group)
		{
			if(!PilotGroups::CheckUserInGroup($pilotid, $group->groupid))
			{
				array_push($freegroups, $group->name);
			}
		}
		
		Template::Set('pilotid', $pilotid);
		Template::Set('pilotgroups', PilotData::GetPilotGroups($pilotid));
		Template::Set('freegroups', $freegroups);
	}
	
	function AddGroup()
	{
		$name = $this->post->name;
		
		if($name == '')
		{
			Template::Set('message', 'You must enter a name!');
		}
		else
		{
			if(PilotGroups::AddGroup($name))
				Template::Set('message', 'The group "'.$name.'" has been added');
			else
				Template::Set('message', 'There was an error!');
		}
		
		Template::Show('core_message.tpl');
	}
	
	function AddPilotToGroup()
	{
		$pilotid = $this->post->pilotid;
		$groupname = $this->post->groupname;
		
		if(PilotGroups::CheckUserInGroup($pilotid, $groupname))
		{
			Template::Set('message', 'This user is already in this group!');
		}
		else
		{
			if(PilotGroups::AddUsertoGroup($pilotid, $groupname))
				Template::Set('message', 'User has been added to the group!');
			else
				Template::Set('message', 'There was an error adding this user');
		}
		
		Template::Show('core_message.tpl');
		
	}
	
	function RemovePilotGroup()
	{
		$pilotid = $this->post->pilotid;
		$groupid = $this->post->groupid;
					
		if(PilotGroups::RemoveUserFromGroup($pilotid, $groupid))
		{
			Template::Set('message', 'Removed');
		}
		else
			Template::Set('message', 'There was an error removing');
			
		Template::Show('core_message.tpl');
	}
	
	function ShowGroups()
	{
		Template::Set('allgroups', PilotGroups::GetAllGroups());
		Template::Show('groups_grouplist.tpl');
		Template::Show('groups_addgroup.tpl');
	}
	
	function ChangePassword()
	{
		$password1 = $this->post->password1;
		$password2 = $this->post->password2;
		
		// Check password length
		if(strlen($password1) <= 5)
		{
			Template::Set('message', 'Password is less than 5 characters');
			Template::Show('core_message.tpl');
			return;
		}
		
		// Check is passwords are the same
		if($password1 != $password2)
		{
			Template::Set('message', 'The passwords do not match');
			Template::Show('core_message.tpl');
			return;
		}
		
		if(RegistrationData::ChangePassword($this->post->pilotid, $password1))
			Template::Set('message', 'Password has been successfully changed');
		else
			Template::Set('message', 'There was an error, administrator has been notified');
			
		Template::Show('core_message.tpl');
	}
}

?>