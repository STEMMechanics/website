import "pinia";
import { UserDetails } from "./UserStore";

declare module "pinia" {
    export interface PiniaCustomProperties {
        setUserDetails(user: UserDetails): void;

        id: string;
        token: string;
        username: string;
        firstName: string;
        lastName: string;
        email: string;
        phone: string;
        permissions: string[];
    }
}
