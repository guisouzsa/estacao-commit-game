import { InertiaLinkProps, Link } from '@inertiajs/react';

export default function NavLink({
    active = false,
    className = '',
    children,
    ...props
}: InertiaLinkProps & { active: boolean }) {
    return (
        <Link
            {...props}
            className={
                'inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none ' +
                (active
                    ? 'border-[#b4da33] text-white focus:border-[#b4da33]'
                    : 'border-transparent text-white/60 hover:border-[#f481c6] hover:text-white focus:border-[#f481c6] focus:text-white') +
                className
            }
        >
            {children}
        </Link>
    );
}
