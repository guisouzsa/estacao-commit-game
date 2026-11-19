import { InputHTMLAttributes } from 'react';

export default function Checkbox({
    className = '',
    ...props
}: InputHTMLAttributes<HTMLInputElement>) {
    return (
        <input
            {...props}
            type="checkbox"
            className={
                'border-2 border-[#1d1d1b] text-[#464498] shadow-[2px_2px_0_#f481c6] focus:ring-[#464498] ' +
                className
            }
        />
    );
}
