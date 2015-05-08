<?php
/*******************************************************************************
 *
 * LEIDEN OPEN VARIATION DATABASE (LOVD)
 *
 * Created     : 2012-11-09
 * Modified    : 2014-01-15
 * For LOVD    : 3.0-10
 *
 * Copyright   : 2004-2014 Leiden University Medical Center; http://www.LUMC.nl/
 * Programmer  : Ing. Ivo F.A.C. Fokkema <I.F.A.C.Fokkema@LUMC.nl>
 *
 *
 * This file is part of LOVD.
 *
 * LOVD is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * LOVD is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with LOVD.  If not, see <http://www.gnu.org/licenses/>.
 *
 *************/

// Don't allow direct access.
if (!defined('ROOT_PATH')) {
    exit;
}



class Feed {
    // Some member variables.
    private $sAtomFeed = '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>
    {{ FEED_TITLE }}
  </title>
  <link rel="alternate" type="text/html" href="{{ LOVD_URL }}"/>
  <link rel="self" type="application/atom+xml" href="{{ FEED_URL }}"/>
  <updated>{{ FEED_DATE_UPDATED }}</updated>
  <id>{{ FEED_ID }}</id>
  <generator uri="http://www.LOVD.nl/" version="{{ LOVD_VERSION }}">
    Leiden Open Variation Database
  </generator>
  <rights>Copyright (c), the curators of this database</rights>
  <entry xmlns="http://www.w3.org/2005/Atom">
    <title>{{ ENTRY_TITLE }}</title>
    <link rel="alternate" type="text/html" href="{{ ENTRY_ALT_URL }}"/>
    <link rel="self" type="application/atom+xml" href="{{ ENTRY_SELF_URL }}"/>
    <id>{{ ENTRY_ID }}</id>
    <author>
      <name>{{ ENTRY_CREATED_BY }}</name>
    </author>
    <contributor>
      <name>{{ ENTRY_EDITED_BY }}</name>
    </contributor>
    <published>{{ ENTRY_DATE_CREATED }}</published>
    <updated>{{ ENTRY_DATE_UPDATED }}</updated>
    <summary>{{ ENTRY_SUMMARY }}</summary>
    <content type="{{ ENTRY_CONTENT_TYPE }}">
      {{ ENTRY_CONTENT }}
    </content>
  </entry>
</feed>';                                                           // The actual text of the feed. We will put variables in there later.
    var $sAtomEntry = '';                                           // Text for an entry. Parsed out of $sAtomFeed.
    var $sAtomEntrySplit = '/([ ]*<entry[> ].*<\/entry>[\r\n]+)/s'; // The preg_* pattern needed to find the entries.
    var $aFeedEntries = array();                                    // This will contain all the various feed entries.
    var $sType = '';                                                // Do we want a Feed, or an Entry?





    // Methods.
    function __construct ($sType = 'feed', $sFeedTitle = '', $sFeedURL = '', $sFeedID = '', $sFormat = 'atom')
    {
        global $_CONF, $_DB, $_SETT, $_STAT;

        // Feed or entry only options.
        if (!in_array($sType, array('feed', 'entry'))) {
            $sType = 'feed'; // Silent error - we just assume Feed when we don't understand the requested type.
        }
        $this->sType = $sType; // So addEntry() knows what to do.

        if (preg_match($this->sAtomEntrySplit, $this->sAtomFeed, $aRegs)) {
            $this->sAtomEntry = $aRegs[1];
        } else {
            // Can't parse own $sAtomFeed, bug in LOVD (or someone has messed with the code).
            lovd_displayError('Feed', 'Couldn\'t parse AtomFeed. This is a bug in LOVD or in one of it\'s modules. Please <A href="' . $_SETT['upstream_URL'] . 'bugs/" target="_blank">file a bug</A> and include the below messages to help us solve the problem.' . "\n" .
                                      'Debug: ' . lovd_getProjectFile() . ($_SERVER['QUERY_STRING']? '?' . $_SERVER['QUERY_STRING'] : ''));
        }

        if ($sType == 'feed') {
            // Fill in the feed's variables.
            $this->sAtomFeed = str_replace('{{ FEED_TITLE }}', $sFeedTitle, $this->sAtomFeed);
            $this->sAtomFeed = str_replace('{{ LOVD_URL }}', ($_CONF['location_url']? $_CONF['location_url'] : lovd_getInstallURL()), $this->sAtomFeed);
            $this->sAtomFeed = str_replace('{{ FEED_URL }}', $sFeedURL, $this->sAtomFeed);
            $this->sAtomFeed = str_replace('{{ FEED_ID }}', ($sFeedID? $sFeedID : 'tag:' . $_SERVER['HTTP_HOST'] . ',' . $_STAT['install_date'] . ':' . $_STAT['signature']), $this->sAtomFeed);
            $this->sAtomFeed = str_replace('{{ LOVD_VERSION }}', $_SETT['system']['version'], $this->sAtomFeed);

            // Let the date of last update depend on the type of feed.
            if (preg_match('/\/variants\/(.+)$/', $sFeedURL, $aRegs)) {
                // Variants of a specific gene.
                $sDateUpdated = $_DB->query('SELECT MAX(updated_date) FROM ' . TABLE_GENES . ' WHERE id = ?', array($aRegs[1]))->fetchColumn();
            } else {
                // Find date of last update for all genes.
                $sDateUpdated = $_DB->query('SELECT MAX(updated_date) FROM ' . TABLE_GENES)->fetchColumn();
            }
            $this->sAtomFeed = str_replace('{{ FEED_DATE_UPDATED }}', $this->formatDate($sDateUpdated), $this->sAtomFeed);

            // For now, remove any of the entries until they are added using addEntry().
            $this->sAtomFeed = preg_replace($this->sAtomEntrySplit, '{{ ENTRY }}', $this->sAtomFeed);
        } else {
            // Only one entry requested.
            // Remove all, except the XML start entity!
            $this->sAtomFeed = preg_replace('/^(.+[\r\n]{1,2})(.|[\r\n]{1,2})+$/', "$1{{ ENTRY }}", $this->sAtomFeed);
        }
    }





    function addEntry ($sTitle, $sSelfURL = '', $sAltURL, $sID, $sAuthor, $sDateCreated, $sContributor = '', $sDateUpdated = '', $sSummary = '', $sContentType = 'text', $sContent = '')
    {
        // Creates an entry, regardless of what kind of. Will be called by other methods that are specialized for a type of entry.

        // Simply start filling in the data.
        $sEntry = $this->sAtomEntry;
        $sEntry = str_replace('{{ ENTRY_TITLE }}', $sTitle, $sEntry);
        $sEntry = str_replace('{{ ENTRY_ALT_URL }}', $sAltURL, $sEntry);
        $sEntry = str_replace('{{ ENTRY_ID }}', $sID, $sEntry);
        $sEntry = str_replace('{{ ENTRY_CREATED_BY }}', $sAuthor, $sEntry);
        $sEntry = str_replace('{{ ENTRY_DATE_CREATED }}', $this->formatDate($sDateCreated), $sEntry);
        if ($sSelfURL) {
            $sEntry = str_replace('{{ ENTRY_SELF_URL }}', $sSelfURL, $sEntry);
        } else {
            // Entries don't have self-URLs, they are useless!
            $sEntry = preg_replace('/.+{{ ENTRY_SELF_URL }}.+[\r\n]{1,2}/', '', $sEntry); // This removes the entire line.
        }
        if ($sContributor) {
            $sEntry = str_replace('{{ ENTRY_EDITED_BY }}', $sContributor, $sEntry);
        } else {
            $sEntry = preg_replace('/.+[\r\n]{1,2}.+{{ ENTRY_EDITED_BY }}.+[\r\n]{1,2}.+[\r\n]{1,2}/', '', $sEntry); // This removes the entire line plus the ones directly before and after.
        }
        if (!$sDateUpdated) {
            $sDateUpdated = $sDateCreated;
        }
        $sEntry = str_replace('{{ ENTRY_DATE_UPDATED }}', $this->formatDate($sDateUpdated), $sEntry);
        if ($sSummary) {
            $sEntry = str_replace('{{ ENTRY_SUMMARY }}', $sSummary, $sEntry);
        } else {
            $sEntry = preg_replace('/.+{{ ENTRY_SUMMARY }}.+[\r\n]{1,2}/', '', $sEntry); // This removes the entire line.
        }
        if ($sContent) {
            if (!in_array($sContentType, array('text', 'html', 'xhtml'))) {
                $sContentType = 'text';
            }
            $sEntry = str_replace('{{ ENTRY_CONTENT_TYPE }}', $sContentType, $sEntry);
            $sEntry = str_replace('{{ ENTRY_CONTENT }}', str_replace("\n", "\n      ", $sContent), $sEntry);
        } else {
            $sEntry = preg_replace('/.+[\r\n]{1,2}.+{{ ENTRY_CONTENT }}.*[\r\n]{1,2}.+[\r\n]{1,2}/', '', $sEntry); // This removes the entire line plus the ones directly before and after.
        }

        if ($this->sType == 'feed') {
            // Add to entry list.
            $this->aFeedEntries[] = $sEntry;
        } else {
            // Overwrite previous entry, if any.
            $this->aFeedEntries = array($sEntry);
        }
    }





    function formatDate ($t)
    {
        // Formats dates (timestamp or formatted) to the format needed for the Atom format.
        if (!preg_match('/^[0-9]+$/', $t)) {
            // Not a timestamp, change to timestamp.
            $t = strtotime($t); // Just assume this works.
        }

        $sDate = date('Y-m-d\TH:i:sO', $t);
        $sDate = substr($sDate, 0, -2) . ':00'; // Needs to be done, because we need +02:00 instead of +0200 (= 'P' in PHP/5.1.3)
        return($sDate);
    }





    function publish ()
    {
        // Publishes the feed, as currently configured, to STDOUT.
        header('Content-type: application/atom+xml;' . ($this->sType == 'entry'? ' type=entry;' : '') . ' charset=UTF-8');
        $this->sAtomFeed = str_replace('{{ ENTRY }}', implode('', $this->aFeedEntries), $this->sAtomFeed);
        die($this->sAtomFeed);
    }
}
?>
