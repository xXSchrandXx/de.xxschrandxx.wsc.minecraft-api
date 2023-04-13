{include file='header' pageTitle='wcf.acp.menu.link.configuration.minecraft.minecraftList'}

<header class="contentHeader">
    <div class="contentHeaderTitle">
        <h1 class="contentTitle">{lang}wcf.acp.menu.link.configuration.minecraft.minecraftList{/lang}</h1>
    </div>

    <nav class="contentHeaderNavigation">
        <ul>
            <li>
				<a href="{link controller='MinecraftAdd'}{/link}" class="button">
					{icon size=16 name='plus' type='solid'} {lang}wcf.acp.menu.link.configuration.minecraft.minecraftList.add{/lang}
				</a>
			</li>
            {event name='contentHeaderNavigation'}
        </ul>
    </nav>
</header>

{hascontent}
<div class="paginationTop">
    {content}
	    {pages print=true assign=pagesLinks controller="MinecraftList" link="pageNo=%d"}
    {/content}
</div>
{/hascontent}

{if $objects|count}
    <div class="section tabularBox">
        <table class="table jsObjectActionContainer" data-object-action-class-name="wcf\data\minecraft\MinecraftAction">
            <thead>
                <tr>
                    <th></th>
                    <th>{lang}wcf.global.objectID{/lang}</th>
                    <th>{lang}wcf.global.title{/lang}</th>
                    <th>{lang}wcf.acp.page.minecraftList.user{/lang}</th>
                    <th>{lang}wcf.acp.page.minecraftList.creationDate{/lang}</th>
                </tr>
            </thead>
            <tbody class="jsReloadPageWhenEmpty">
                {foreach from=$objects item=object}
                    <tr class="jsObjectActionObject" data-object-id="{@$object->getObjectID()}">
                        <td class="columnIcon">
                            <a href="{link controller='MinecraftEdit' id=$object->getObjectID()}{/link}"
                                title="{lang}wcf.global.button.edit{/lang}" class="jsTooltip">
								{icon size=16 name='pencil' type='solid'}
							</a>
                            {objectAction action="delete" objectTitle=$object->getTitle()}
                            {event name='rowButtons'}
                        </td>
                        <td class="columnID">{#$object->getObjectID()}</td>
                        <td class="columnTitle">{$object->getTitle()}</td>
						<td class="columnUsername">{$object->getUser()}</td>
                        <td class="columnDate">{@$object->getCreatedTimestamp()|time}</td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
{else}
    <p class="info">{lang}wcf.global.noItems{/lang}</p>
{/if}

{include file='footer'}
