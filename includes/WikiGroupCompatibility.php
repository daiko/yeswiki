<?php
namespace YesWiki;

class WikiGroupCompatibility
{
    public $groupsCache = array();

    /**
     *
     * @return array The list of all group names
     */
    public function getGroupsList()
    {
        $groups = $this->groupFactory->getAll();
        $list = array();
        foreach ($groups as $group) {
            $list[] = $group->name;
        }
        return $list;
    }

    /**
     *
     * @param string $group
     *            The name of a group
     * @return boolean true iff the user is in the given $group
     */
    public function userIsInGroup($group, $user = null, $admincheck = true)
    {
        if (is_null($user)) {
            $user = $this->connectedUser;
        } else {
            if (!$this->userFactory->isExist($user)) {
                // L'utilisateur n'existe pas.
                return false;
            }
            // TODO : Attention hack possible si la machine porte le nom d'un
            // utilisateur existant il est possible de se faire passer pour lui.
            $user = $this->userFactory->get($user);
        }

        $group = $this->groupFactory->get($group);
        if ($group->isMember($user)) {
            return true;
        }
        return false;
    }

    /**
     *
     * @param string $group
     *            The name of a group
     * @return string the ACL associated with the group $gname
     * @see userIsInGroup to check if a user belongs to some group
     */
    public function getGroupACL($group)
    {
        if (array_key_exists($group, $this->groupsCache)) {
            return $this->groupsCache[$group];
        }
        return $this->groupsCache[$group] =
            $this->triples->getTripleValue($group, WIKINI_VOC_ACLS, GROUP_PREFIX);
    }

    /**
     * Créé un nouveau groupe
     *
     * @param string $gname
     *            The name of a group
     * @param string $acl
     *            The new ACL to associate with the group $gname
     * @return int 0 if successful, a triple error code or a specific error code:
     *         1000 if the new value would define the group recursively
     *         1001 if $gname is not named with alphanumeric chars
     * @see getGroupACL
     */
    public function setGroupACL($gname, $acl)
    {
        if (preg_match('/[^A-Za-z0-9]/', $gname)) {
            return 1001;
        }
        $old = $this->getGroupACL($gname);
        if ($this->makesGroupRecursive($gname, $acl)) {
            return 1000;
        }
        $this->groupsCache[$gname] = $acl;
        if ($old === null) {
            return $this->triples->insertTriple($gname, WIKINI_VOC_ACLS, $acl, GROUP_PREFIX);
        } elseif ($old === $acl) {
            return 0; // nothing has changed
        }
        return $this->updateTriple($gname, WIKINI_VOC_ACLS, $old, $acl, GROUP_PREFIX);
    }

    /**
     * Checks if a new group acl is not defined recursively
     * (this method expects that groups that are already defined are not
     * themselves defined recursively...)
     *
     * @param string $gname
     *            The name of the group
     * @param string $acl
     *            The new acl for that group
     * @return boolean True iff the new acl defines the group recursively
     */
    public function makesGroupRecursive($gname, $acl, $origin = null, $checked = array())
    {
        $gname = strtolower($gname);
        if ($origin === null) {
            $origin = $gname;
        } elseif ($gname === $origin) {
            return true;
        }

        foreach (explode("\n", $acl) as $line) {
            if (!$line) {
                continue;
            }

            if ($line[0] == '!') {
                $line = substr($line, 1);
            }

            if (!$line) {
                continue;
            }

            if ($line[0] == '@') {
                $line = substr($line, 1);
                if (! in_array($line, $checked)) {
                    if ($this->makesGroupRecursive(
                        $line,
                        $this->getGroupACL($line),
                        $origin,
                        $checked
                    )) {
                        return true;
                    }
                }
            }
        }
        $checked[] = $gname;
        return false;
    }
}