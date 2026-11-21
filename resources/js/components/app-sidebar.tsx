import { Link } from '@inertiajs/react';
import { CalendarDays, FolderGit2, Scissors, Users } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { agenda } from '@/routes';
import { index as profissionais } from '@/routes/profissionais';
import { index as servicos } from '@/routes/servicos';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    { title: 'Agenda', href: agenda(), icon: CalendarDays },
    { title: 'Profissionais', href: profissionais(), icon: Users },
    { title: 'Serviços', href: servicos(), icon: Scissors },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Código no GitHub',
        href: 'https://github.com/rafaelamiranda/agendamentos-laravel',
        icon: FolderGit2,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={agenda()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
