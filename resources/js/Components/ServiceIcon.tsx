import Avatar from "@mui/material/Avatar";
import Icon from "./Icon";

interface ServiceIconProps {
    /** サービス名。画像が無いときの頭文字に使う */
    name: string;
    /** アイコンの URL。未設定なら null */
    iconUrl: string | null;
    /** 一辺の大きさ */
    size?: number;
}

/**
 * サービスのアイコン。
 *
 * 画像は外部に置かれているので、読めないことがある。そのときは
 * 名前の頭文字に落とす。読み込めない画像の枠が残るより分かりやすい。
 */
export default function ServiceIcon({ name, iconUrl, size = 32 }: ServiceIconProps) {
    const initial = name.trim().charAt(0);

    return (
        <Avatar
            src={iconUrl ?? undefined}
            alt=""
            variant="rounded"
            sx={{ width: size, height: size, fontSize: size * 0.45, bgcolor: "action.hover", color: "text.secondary" }}
        >
            {initial !== "" ? initial : <Icon name="plug" />}
        </Avatar>
    );
}
