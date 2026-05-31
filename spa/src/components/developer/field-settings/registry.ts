import type { SettingControl } from "@/api/endpoints/field-types";

import { BoolControl } from "./BoolControl";
import { CalloutGroupsControl } from "./CalloutGroupsControl";
import { ColumnSelectControl } from "./ColumnSelectControl";
import { DirectoryControl } from "./DirectoryControl";
import { EnumControl } from "./EnumControl";
import { HeadingControl } from "./HeadingControl";
import { ImageOptionsControl } from "./ImageOptionsControl";
import { IntControl } from "./IntControl";
import { ListMakerControl } from "./ListMakerControl";
import { MatrixColumnsControl } from "./MatrixColumnsControl";
import { NoteControl } from "./NoteControl";
import { SourceFieldsControl } from "./SourceFieldsControl";
import { StringControl } from "./StringControl";
import { TableSelectControl } from "./TableSelectControl";
import { TextareaControl } from "./TextareaControl";
import type { ControlComponent } from "./types";

/** Maps a settings_schema `control` id to its React control component. */
export const controlRegistry: Record<SettingControl, ControlComponent> = {
	string: StringControl,
	int: IntControl,
	textarea: TextareaControl,
	bool: BoolControl,
	enum: EnumControl,
	note: NoteControl,
	heading: HeadingControl,
	directory: DirectoryControl,
	db_table: TableSelectControl,
	db_column: ColumnSelectControl,
	db_column_sort: ColumnSelectControl,
	list_maker: ListMakerControl,
	source_fields: SourceFieldsControl,
	callout_groups: CalloutGroupsControl,
	image_options: ImageOptionsControl,
	matrix_columns: MatrixColumnsControl,
};
